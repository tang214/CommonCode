<?php
namespace Auth;
if(!class_exists('Httpful\Request'))
{
    require(realpath(dirname(__FILE__)).'/../libs/httpful/bootstrap.php');
}

class FlipsideAPIUser extends User
{
    private $userData;
    private $groupData = null;

    public function __construct($data = false)
    {
        if(($data !== false) && !isset($data['extended']))
        {
            //Generic user object
            //TODO get from API
        }
        else
        {
            if(isset($data['extended']))
            {
                $this->userData = $data['extended'];
            }
        }
    }

    public function isInGroupNamed($name)
    {
        if($this->groupData === null)
        {
            $resp = \Httpful\Request::get('https://profiles.test.burningflipside.com/api/v1/users/me/groups')->authenticateWith($this->userData->uid, $this->userData->userPassword)->send();
            if($resp->hasErrors())
            {
                return false;
            }
            $this->groupData = $resp->body;
        }
        $count = count($this->groupData);
        for($i = 0; $i < $count; $i++)
        {
            if($this->groupData[$i]->cn === $name)
            {
                return true;
            }
        }
        return false;
    }

    public function __get($propName)
    {
        if($this->userData === null)
        {
            return parent::__get($propName);
        }
        $propName = strtolower($propName);
        return $this->userData->{$propName};
    }

    public function __set($propName, $value)
    {
    }
}

