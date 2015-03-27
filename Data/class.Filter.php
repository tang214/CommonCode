<?php
namespace Data;

class Filter
{
    private $children = array();
    private $string;

    function __construct($string = false)
    {
        if($string !== false)
        {
            $this->string = $string;
            $this->children = self::process_string($this->string);
        }
    }

    static public function process_string($string)
    {
        $and = strstr($string, ' and ');
        $or  = strstr($string, ' or ');
        if($and === false && $or === false)
        {
            return array(new FilterClause($string));
        }
        else if($and !== false)
        {
            $and_pos  = strpos($string, $and);
            $first    = substr($string, 0, $and_pos);
            $second   = substr($string, $and_pos+5);
            $children = array();
            array_push($children, new FilterClause($first));
            array_push($children, 'and');
            $children = array_merge($children, self::process_string($second));
            return $children;
        }
        else if($or !== false)
        {
            $or_pos   = strpos($string, $or);
            $first    = substr($string, 0, $or_pos);
            $second   = substr($string, $or_pos+4);
            $children = array();
            array_push($children, new FilterClause($first));
            array_push($children, 'or');
            $children = array_merge($children, self::process_string($second));
            return $children;
        }
        else
        {
            $and_pos = strpos($string, $and);
            $or_pos  = strpos($string, $or);
            if($and_pos < $or_pos)
            {
                $first    = substr($string, 0, $and_pos);
                $second   = substr($string, $and_pos+5);
                $children = array();
                array_push($children, new FilterClause($first));
                array_push($children, 'and');
                $children = array_merge($children, self::process_string($second));
                return $children;
            }
            else
            {
                $first    = substr($string, 0, $or_pos);
                $second   = substr($string, $or_pos+4);
                $children = array();
                array_push($children, new FilterClause($first));
                array_push($children, 'or');
                $children = array_merge($children, self::process_string($second));
                return $children;
            }
        }
    }

    function to_sql_string()
    {
        $ret = '';
        $count = count($this->children);
        for($i = 0; $i < $count; $i++)
        {
            if($this->children[$i] === 'and')
            {
                $ret.=' AND ';
            }
            else if($this->children[$i] === 'or')
            {
                $ret.=' OR ';
            }
            else
            {
                $ret.=$this->children[$i]->to_sql_string();
            }
        }
        return $ret;
    }

    function filter_array(&$array)
    {
        $res = array();
        if(is_array($array))
        {
            $search = $array;
            $count = count($this->children);
            for($i = 0; $i < $count; $i++)
            {
                if($this->children[$i] === 'and')
                {
                    $search = $res;
                }
                else if($this->children[$i] === 'or')
                {
                    $search = $array;
                }
                else
                {
                    foreach($search as $subarray)
                    {
                        if(isset($subarray[$this->children[$i]->var1]))
                        {
                            if($this->children[$i]->php_compare($subarray[$this->children[$i]->var1]))
                            {
                                array_push($res, $subarray);
                            }
                        }
                    }
                }
            }
        }
        return $res;
    }
}
?>
