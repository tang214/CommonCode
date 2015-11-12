<?php
namespace Auth;
require_once("/var/www/secure_settings/class.FlipsideSettings.php");

function sort_array(&$array, $orderby)
{
    $count = count($array);
    $keys  = array_keys($orderby);
    for($i = 0; $i < $count; $i++)
    {
        for($j = $i; $j < $count; $j++)
        {
            $d = strcasecmp($array[$i][$keys[0]][0], $array[$j][$keys[0]][0]);
            switch($orderby[$keys[0]])
            {
                case 1:
                    if($d > 0) swap($array, $i, $j);
                    break;
                case 0:
                    if($d < 0) swap($array, $i, $j);
                    break;
            }
        }
    }
}

function swap(&$array, $i, $j)
{
    $tmp = $array[$i];
    $array[$i] = $array[$j];
    $array[$j] = $tmp;
}

class LDAPAuthenticator extends Authenticator
{
    private $host;
    public  $user_base;
    public  $group_base;
    private $bind_dn;
    private $bind_pass;

    public function __construct($params)
    {
        parent::__construct($params);
        if(isset($params['host']))
        {
            $this->host = $params['host'];
        }
        else
        {
            if(isset(\FlipsideSettings::$ldap['proto']))
            {
                $this->host = \FlipsideSettings::$ldap['proto'].'://'.\FlipsideSettings::$ldap['host'];
            }
            else
            {
                $this->host = \FlipsideSettings::$ldap['host'];
            }
        }
        if(isset($params['user_base']))
        {
           $this->user_base = $params['user_base'];
        }
        else
        {
            $this->user_base = \FlipsideSettings::$ldap['user_base'];
        }
        if(isset($params['group_base']))
        {
            $this->group_base = $params['group_base'];
        }
        else
        {
            $this->group_base = \FlipsideSettings::$ldap['group_base'];
        }
        if(isset($params['bind_dn']))
        {
            $this->bind_dn = $params['bind_dn'];
        }
        else
        {
            $this->bind_dn = \FlipsideSettings::$ldap_auth['read_write_user'];
        }
        if(isset($params['bind_pass']))
        {
            $this->bind_pass = $params['bind_pass'];
        }
        else
        {
            $this->bind_pass = \FlipsideSettings::$ldap_auth['read_write_pass'];
        }
    }

    public function get_and_bind_server($bind_write=false)
    {
        $server = \LDAP\LDAPServer::getInstance();
        $server->user_base = $this->user_base;
        $server->group_base = $this->group_base;
        $server->connect($this->host);
        if($bind_write === false)
        {
            $ret = $server->bind();
        }
        else
        {
            $ret = $server->bind($this->bind_dn, $this->bind_pass);
        }
        if($ret === false)
        {
            return false;
        }
        return $server;
    }

    public function login($username, $password)
    {
        $server = $this->get_and_bind_server();
        if($server === false)
        {
            return false;
        }
        $filter = new \Data\Filter("uid eq $username or mail eq $username");
        $user = $server->read($this->user_base, $filter);
        if($user === false || count($user) === 0)
        {
            return false;
        }
        $user = $user[0];
        $server->unbind();
        $ret = $server->bind($user->dn, $password);
        if($ret !== false)
        {
            return array('res'=>true, 'extended'=>$user); 
        }
        return false;
    }

    public function isLoggedIn($data)
    {
        if(isset($data['res']))
        {
            return $data['res'];
        }
        return false;
    }

    public function getUser($data)
    {
        return new LDAPUser($data);
    }

    public function getGroupByName($name)
    {
        $server = $this->get_and_bind_server();
        if($server === false)
        {
            return false;
        }
        return LDAPGroup::from_name($name, $server);
    }

    public function getGroupsByFilter($filter, $select=false, $top=false, $skip=false, $orderby=false)
    {
        $server = $this->get_and_bind_server();
        if($server === false)
        {
            return false;
        }
        if($filter === false)
        {
            $filter = new \Data\Filter('cn eq *');
        }
        $groups = $server->read($this->group_base, $filter);
        if($groups === false)
        {
            return false;
        }
        $count = count($groups);
        for($i = 0; $i < $count; $i++)
        {
            $groups[$i] = new LDAPGroup($groups[$i]);
            if($select !== false)
            {
                $groups[$i] = json_decode(json_encode($groups[$i]), true);
                $groups[$i] = array_intersect_key($groups[$i], array_flip($select));
            }
        }
        return $groups;
    }

    public function getActiveUserCount()
    {
        $server = $this->get_and_bind_server();
        if($server === false)
        {
            return false;
        }
        return $server->count($this->user_base);
    }

    public function getUsersByFilter($filter, $select=false, $top=false, $skip=false, $orderby=false)
    {
        $server = $this->get_and_bind_server();
        if($server === false)
        {
            return false;
        }
        if($filter === false)
        {
            $filter = new \Data\Filter('cn eq *');
        }
        $users = $server->read($this->user_base, $filter);
        if($users === false)
        {
            return false;
        }
        $count = count($users);
        if($orderby !== false)
        {
            sort_array($users, $orderby);
        }
        if($select !== false)
        {
            $select = array_flip($select);
        }
        if($skip !== false && $top !== false)
        {
            $users = array_slice($users, $skip, $top);
        }
        else if($top !== false)
        {
            $users = array_slice($users, 0, $top);
        }
        else if($skip !== false)
        {
            $users = array_slice($users, $skip);
        }
        $count = count($users);
        for($i = 0; $i < $count; $i++)
        {
            $tmp = new LDAPUser($users[$i]);
            if($select !== false)
            {
                $tmp = $tmp->jsonSerialize();
                $tmp = array_intersect_key($tmp, $select);
            }
            $users[$i] = $tmp;
        }
        return $users;
    }

    public function activatePendingUser($user)
    {
        $this->get_and_bind_server(true);
        $new_user = new LDAPUser();
        $new_user->setUID($user->getUID());
        $email = $user->getEmail();
        $new_user->setEmail($email);
        $pass = $user->getPassword();
        if($pass !== false)
        {
            $new_user->setPass($pass);
        }
        $sn = $user->getLastName();
        if($sn !== false)
        {
            $new_user->setLastName($sn);
        }
        $givenName = $user->getGivenName();
        if($givenName !== false)
        {
            $new_user->setGivenName($givenName);
        }
        $hosts = $user->getLoginProviders();
        if($hosts !== false)
        {
            $count = count($hosts);
            for($i = 0; $i < $count; $i++)
            {
                $new_user->addLoginProvider($hosts[$i]);
            }
        }
        $ret = $new_user->flushUser();
        if($ret)
        {
            $user->delete();
        }
        $users = $this->getUsersByFilter(new \Data\Filter('mail eq '.$email));
        if($users === false || !isset($users[0]))
        {
            throw new \Exception('Error creating user!');
        }
        return $users[0];
    }

    public function getUserByResetHash($hash)
    {
        $users = $this->getUsersByFilter(new \Data\Filter("uniqueIdentifier eq $hash"));
        if($users === false || !isset($users[0]))
        {
            return false;
        }
        return $users[0];
    }
}
/* vim: set tabstop=4 shiftwidth=4 expandtab: */
?>
