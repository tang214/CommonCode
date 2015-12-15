<?php
/**
 * AuthProvider class
 *
 * This file describes the AuthProvider Singleton
 *
 * PHP version 5 and 7
 *
 * @author Patrick Boyd / problem@burningflipside.com
 * @copyright Copyright (c) 2015, Austin Artistic Reconstruction
 * @license http://www.apache.org/licenses/ Apache 2.0 License
 */

/**
 * Allow other classes to be loaded as needed
 */
require_once('Autoload.php');
/**
 * Require the FlipsideSettings file
 */
require_once '/var/www/secure_settings/class.FlipsideSettings.php';

/**
 * A Singleton class to abstract access to the authentication providers.
 *
 * This class is the primary method to access user data, login, and other authenication information.
 */
class AuthProvider extends Singleton
{
    /** The authentication methods loaded by the provider */
    protected $methods;

    /**
     * Load the authentrication providers specified in the FlipsideSettings::$authProviders array
     */
    protected function __construct()
    {
        $this->methods = array();
        if(isset(FlipsideSettings::$authProviders))
        {
            $keys = array_keys(FlipsideSettings::$authProviders);
            $count = count($keys);
            for($i = 0; $i < $count; $i++)
            {
                $class = $keys[$i];
                array_push($this->methods, new $class(FlipsideSettings::$authProviders[$keys[$i]]));
            }
        }
    }

    /**
     * Get the Authenticator class instance by name
     *
     * @param string $methodName The class name of the Authenticator to get the instance for
     *
     * @return Auth\Authenticator|false The specified Authenticator class instance or false if it is not loaded
     */
    public function getAuthenticator($methodName)
    {
        $count = count($this->methods);
        for($i = 0; $i < $count; $i++)
        {
            if(strcasecmp(get_class($this->methods[$i]), $methodName) === 0)
            {
                return $this->methods[$i];
            }
        }
        return false;
    }

    /**
     * Get the Auth\User class instance for the specified login
     *
     * Unlike the AuthProvider::login() function. This function will not impact the SESSION
     *
     * @param string $username The username of the User
     * @param string $password The password of the User
     *
     * @return Auth\User|false The User with the specified credentials or false if the credentials are not valid
     */
    public function getUserByLogin($username, $password)
    {
        $res = false;
        $count = count($this->methods);
        for($i = 0; $i < $count; $i++)
        {
            $res = $this->methods[$i]->login($username, $password);
            if($res !== false)
            {
                return $this->methods[$i]->getUser($res);
            }
        }
        return $res;
    }

    /**
     * Use the provided credetials to log the user on
     *
     * @param string $username The username of the User
     * @param string $password The password of the User
     *
     * @return true|false true if the login was successful, false otherwise
     */
    public function login($username, $password)
    {
        $res = false;
        $count = count($this->methods);
        for($i = 0; $i < $count; $i++)
        {
            $res = $this->methods[$i]->login($username, $password);
            if($res !== false)
            {
                FlipSession::setVar('AuthMethod', get_class($this->methods[$i]));
                FlipSession::setVar('AuthData', $res);
                break;
            }
        }
        return $res;
    }

    /**
     * Determine if the user is still logged on from the session data
     *
     * @param stdClass $data The AuthData from the session
     * @param string $methodName The AuthMethod from the session
     *
     * @return true|false true if user is logged on, false otherwise
     */
    public function isLoggedIn($data, $methodName)
    {
        $auth = $this->getAuthenticator($methodName);
        return $auth->isLoggedIn($data);
    }

    /**
     * Obtain the currently logged in user from the session data
     *
     * @param stdClass $data The AuthData from the session
     * @param string $methodName The AuthMethod from the session
     *
     * @return Auth\User|false The User instance if user is logged on, false otherwise
     */
    public function getUser($data, $methodName)
    {
        $auth = $this->getAuthenticator($methodName);
        return $auth->getUser($data);
    }

    /**
     * Get an Auth\Group by its name
     *
     * @param string $name The name of the group
     * @param string $methodName The AuthMethod if information is desired only from a particular Auth\Authenticator
     *
     * @return Auth\Group|false The Group instance if a group with that name exists, false otherwise
     */
    public function getGroupByName($name, $methodName = false)
    {
        if($methodName === false)
        {
            $ret = false;
            $res = false;
            $count = count($this->methods);
            for($i = 0; $i < $count; $i++)
            {
                if($this->methods[$i]->current === false) continue;

                $res = $this->methods[$i]->getGroupByName($name);
                if($res !== false)
                {
                    if($ret === false)
                    {
                        $ret = $res;
                    }
                    else
                    {
                        $ret->merge($res);
                    }
                }
            }
            return $ret;
        }
        else
        {
            $auth = $this->getAuthenticator($methodName);
            return $auth->getGroupByName($name);
        }
    }

    /**
     * Get an array of Auth\User from a filtered set
     *
     * @param Data\Filter|false $filter The filter conditions or false to retreive all
     * @param array|false $methodName The user fields to obtain or false to obtain all
     * @param integer|false $top The number of users to obtain or false to obtain all
     * @param integer|false $skip The number of users to skip or false to skip none
     * @param array|false $orderby The field to sort by and the method to sort or false to not sort
     * @param string|false $methodName The AuthMethod if information is desired only from a particular Auth\Authenticator
     *
     * @return array|false An array of Auth\User objects or false if no users were found
     */
    public function getUsersByFilter($filter, $select=false, $top=false, $skip=false, $orderby=false, $methodName = false)
    {
        if($methodName === false)
        {
            $ret = false;
            $res = false;
            $count = count($this->methods);
            for($i = 0; $i < $count; $i++)
            {
                if($this->methods[$i]->current === false) continue;

                $res = $this->methods[$i]->getUsersByFilter($filter, $select, $top, $skip, $orderby);
                if($res !== false)
                {
                    if($ret === false)
                    {
                        $ret = $res;
                    }
                    else
                    {
                        $ret->merge($res);
                    }
                }
            }
            return $ret;
        }
        else
        {
            $auth = $this->getAuthenticator($methodName);
            return $auth->getUsersByFilter($filter, $select, $top, $skip, $orderby);
        }
    }

    /**
     * Get an array of Auth\PendingUser from a filtered set
     *
     * @param Data\Filter|false $filter The filter conditions or false to retreive all
     * @param array|false $methodName The user fields to obtain or false to obtain all
     * @param integer|false $top The number of users to obtain or false to obtain all
     * @param integer|false $skip The number of users to skip or false to skip none
     * @param array|false $orderby The field to sort by and the method to sort or false to not sort
     * @param string|false $methodName The AuthMethod if information is desired only from a particular Auth\Authenticator
     *
     * @return array|false An array of Auth\PendingUser objects or false if no pending users were found
     */
    public function getPendingUsersByFilter($filter, $select=false, $top=false, $skip=false, $orderby=false, $methodName = false)
    {
        if($methodName === false)
        {
            $ret = false;
            $res = false;
            $count = count($this->methods);
            for($i = 0; $i < $count; $i++)
            {
                if($this->methods[$i]->pending === false) continue;

                $res = $this->methods[$i]->getPendingUsersByFilter($filter, $select, $top, $skip, $orderby);
                if($res !== false)
                {
                    if($ret === false)
                    {
                        $ret = $res;
                    }
                    else
                    {
                        $ret->merge($res);
                    }
                }
            }
            return $ret;
        }
        else
        {
            $auth = $this->getAuthenticator($methodName);
            return $auth->getPendingUsersByFilter($filter, $select, $top, $skip, $orderby);
        }
    }

    /**
     * Get an array of Auth\Group from a filtered set
     *
     * @param Data\Filter|false $filter The filter conditions or false to retreive all
     * @param array|false $methodName The group fields to obtain or false to obtain all
     * @param integer|false $top The number of groups to obtain or false to obtain all
     * @param integer|false $skip The number of groups to skip or false to skip none
     * @param array|false $orderby The field to sort by and the method to sort or false to not sort
     * @param string|false $methodName The AuthMethod if information is desired only from a particular Auth\Authenticator
     *
     * @return array|false An array of Auth\Group objects or false if no pending users were found
     */
    public function getGroupsByFilter($filter, $select=false, $top=false, $skip=false, $orderby=false, $methodName = false)
    {
        if($methodName === false)
        {
            $ret = false;
            $res = false;
            $count = count($this->methods);
            for($i = 0; $i < $count; $i++)
            {
                if($this->methods[$i]->current === false) continue;

                $res = $this->methods[$i]->getGroupsByFilter($filter, $select, $top, $skip, $orderby);
                if($res !== false)
                {
                    if($ret === false)
                    {
                        $ret = $res;
                    }
                    else
                    {
                        $ret->merge($res);
                    }
                }
            }
            return $ret;
        }
        else
        {
            $auth = $this->getAuthenticator($methodName);
            return $auth->getGroupsByFilter($filter, $select, $top, $skip, $orderby);
        }
    }

    /**
     * Get the number of currently active users on the system
     *
     * @param string|false $methodName The AuthMethod if information is desired only from a particular Auth\Authenticator
     *
     * @return integer The number of currently active users on the system
     */
    public function getActiveUserCount($methodName = false)
    {
        if($methodName === false)
        {
            $userCount = 0;
            $count = count($this->methods);
            for($i = 0; $i < $count; $i++)
            {
                if($this->methods[$i]->current === false) continue;

                $userCount += $this->methods[$i]->getActiveUserCount();
            }
            return $userCount;
        }
        else
        {
            $auth = $this->getAuthenticator($methodName);
            return $auth->getActiveUserCount();
        }
    }

    /**
     * Get the number of currently pending users on the system
     *
     * @param string|false $methodName The AuthMethod if information is desired only from a particular Auth\Authenticator
     *
     * @return integer The number of currently pending users on the system
     */
    public function getPendingUserCount($methodName = false)
    {
        if($methodName === false)
        {
            $userCount = 0;
            $count = count($this->methods);
            for($i = 0; $i < $count; $i++)
            {
                if($this->methods[$i]->pending === false) continue;

                $userCount += $this->methods[$i]->getPendingUserCount();
            }
            return $userCount;
        }
        else
        {
            $auth = $this->getAuthenticator($methodName);
            return $auth->getPendingUserCount();
        }
    }

    /**
     * Get the number of current groups on the system
     *
     * @param string|false $methodName The AuthMethod if information is desired only from a particular Auth\Authenticator
     *
     * @return integer The number of current groups on the system
     */
    public function getGroupCount($methodName = false)
    {
        if($methodName === false)
        {
            $groupCount = 0;
            $count = count($this->methods);
            for($i = 0; $i < $count; $i++)
            {
                if($this->methods[$i]->current === false) continue;

                $groupCount += $this->methods[$i]->getGroupCount();
            }
            return $groupCount;
        }
        else
        {
            $auth = $this->getAuthenticator($methodName);
            return $auth->getGroupCount();
        }
    }

    /**
     * Get the login links for all supplementary Authenitcation mechanisms
     *
     * This will return an array of links to any supplementary authentication mechanims. For example, Goodle is 
     * a supplementary authentication mechanism.
     *
     * @return array An array of suppmentary authentication mechanism links
     */
    public function getSupplementaryLinks()
    {
        $ret = array();
        $count = count($this->methods);
        for($i = 0; $i < $count; $i++)
        {
            if($this->methods[$i]->supplement === false) continue;

            array_push($ret, $this->methods[$i]->getSupplementLink());
        }
        return $ret;
    }

    /**
     * Impersonate the user specified
     *
     * This will replace the user in the session with the specified user. In order
     * to undo this operation a user must logout.
     *
     * @param array|Auth\User $userArray Data representing the user
     */
    public function impersonateUser($userArray)
    {
        if(is_object($userArray))
        {
            \FlipSession::setUser($userArray);
        }
        else
        {
            $user = new $userArray['class']($userArray);
            \FlipSession::setUser($user);
        }
    }

    /**
     * Get the pending user reresented by the supplied hash
     *
     * @param string $hash The hash value representing the Penging User
     * @param string|false $methodName The AuthMethod if information is desired only from a particular Auth\Authenticator
     *
     * @return Auth\PendingUser|false The Auth\PendingUser instance or false if no user is matched by the provided hash
     */
    public function getTempUserByHash($hash, $methodName = false)
    {
        if($methodName === false)
        {
            $count = count($this->methods);
            for($i = 0; $i < $count; $i++)
            {
                if($this->methods[$i]->pending === false) continue;

                $ret = $this->methods[$i]->getTempUserByHash($hash);
                if($ret !== false)
                {
                    return true;
                }
            }
            return false;
        }
        else
        {
            $auth = $this->getAuthenticator($methodName);
            return $auth->getTempUserByHash($hash);
        }
    }

    /**
     * Create a pending user
     *
     * @param array $user An array of information about the user to create
     * @param string|false $methodName The AuthMethod if information is desired only from a particular Auth\Authenticator
     *
     * @return true|false true if the user was successfully created. Otherwise false.
     */
    public function createPendingUser($user, $methodName = false)
    {
        if($methodName === false)
        {
            $count = count($this->methods);
            for($i = 0; $i < $count; $i++)
            {
                if($this->methods[$i]->pending === false) continue;

                $ret = $this->methods[$i]->createPendingUser($user);
                if($ret !== false)
                {
                    return true;
                }
            }
            return false;
        }
        else
        {
            $auth = $this->getAuthenticator($methodName);
            return $auth->createPendingUser($user);
        }
    }

    /**
     * Convert a Auth\PendingUser into an Auth\User
     *
     * This will allow a previously pending user the ability to log on in the future as an active user. It will also
     * have the side effect of logging the user on now.
     *
     * @param Auth\PendingUser $user The user to turn into a current user
     * @param string|false $methodName The AuthMethod if information is desired only from a particular Auth\Authenticator
     *
     * @return true|false true if the user was successfully created. Otherwise false.
     */
    public function activatePendingUser($user, $methodName = false)
    {
        if($methodName === false)
        {
            $count = count($this->methods);
            for($i = 0; $i < $count; $i++)
            {
                if($this->methods[$i]->current === false) continue;

                $ret = $this->methods[$i]->activatePendingUser($user);
                if($ret !== false)
                {
                    $this->impersonate_user($ret);
                    return true;
                }
            }
            return false;
        }
        else
        {
            $auth = $this->getAuthenticator($methodName);
            return $auth->activatePendingUser($user);
        }
    }

    /**
     * Get a current user by a password reset hash
     *
     * @param string $hash The current password reset hash for the user
     * @param string|false $methodName The AuthMethod if information is desired only from a particular Auth\Authenticator
     *
     * @return Auth\User|false The user if the password reset hash is valid. Otherwise false.
     */
    public function getUserByResetHash($hash, $methodName = false)
    {
        if($methodName === false)
        {
            $count = count($this->methods);
            for($i = 0; $i < $count; $i++)
            {
                if($this->methods[$i]->current === false) continue;

                $ret = $this->methods[$i]->getUserByResetHash($hash);
                if($ret !== false)
                {
                    return $ret;
                }
            }
            return false;
        }
        else
        {
            $auth = $this->getAuthenticator($methodName);
            return $auth->getUserByResetHash($hash);
        }
    }

    /**
     * Get the Auth\Authenticator by host name
     *
     * @param string $host The host name used by the supplemental authentication mechanism
     *
     * @return Auth\Authenticator|false The Authenticator if the host is supported by a loaded Authenticator. Otherwise false.
     */
    public function getSuplementalProviderByHost($host)
    {
        $count = count($this->methods);
        for($i = 0; $i < $count; $i++)
        {
            if($this->methods[$i]->supplement === false) continue;

            if($this->methods[$i]->getHostName() === $host)
            {
                return $this->methods[$i];
            }
        }
        return false;
    }
}
/* vim: set tabstop=4 shiftwidth=4 expandtab: */
?>