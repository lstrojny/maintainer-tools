<?php
namespace lstrojny\Maintenance\Value;

use function is_scalar;

class TreeNode
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function __isset($property) : bool
    {
        return $this->data[$property];
    }

    public function __get($property) : TreeNode
    {
        return new TreeNode($this->data[$property] ?? null);
    }

    public function get()
    {
        return $this->data;
    }

    public function __toString() : string
    {
        return is_scalar($this->data) ? (string) $this->data : json_encode($this->data);
    }
}
