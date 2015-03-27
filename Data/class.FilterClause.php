<?php
namespace Data;

class FilterClause
{
    public $var1;
    public $var2;
    public $op;

    function __construct($string)
    {
        $this->process_filter_string($string);
    }

    static function str_startswith($haystack, $needle)
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    protected function process_filter_string($string)
    {
        if(self::str_startswith($string, 'substringof'))
        {
            $this->op   = strtok($string, '(');
            $this->var1 = strtok(',');
            $this->var2 = strtok(')');
            return;
        }
        $field = strtok($string, ' ');
        $op = strtok(' ');
        $rest = strtok("\0");
        switch($op)
        {
            case 'ne':
                $op = '!=';
                break;
            case 'eq':
                $op = '=';
                break;
            case 'lt':
                $op = '<';
                break;
            case 'le':
                $op = '<=';
                break;
            case 'gt':
                $op = '>';
                break;
            case 'ge':
                $op = '>=';
                break;
        }
        $this->var1  = $field;
        $this->op    = $op;
        $this->var2  = $rest;
    }

    function to_sql_string()
    {
        $str = '';
        switch($this->op)
        {
            case 'substringof':
                $str = $this->var1.' LIKE \'%'.trim($this->var2,"'").'%\'';
                break;
            default:
                $str = $this->var1.$this->op.$this->var2;
                break;
        }
        return $str;
    }

    function php_compare($value)
    {
        switch($this->op)
        {
            case '!=':
                return $value != $this->var2;
            case '=':
                return $value == $this->var2;
            case '<':
                return $value < $this->var2;
            case '<=':
                return $value <= $this->var2;
            case '>':
                return $value > $this->var2;
            case '>=':
                return $value >= $this->var2;
        }
    }
}
?>
