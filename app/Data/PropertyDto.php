<?php

namespace App\Data;

use App\Console\Commands\GenerateDtoCommand;

class PropertyDto extends \Spatie\LaravelData\Data
{
    public array $data = [];
    public array $types = [];

    public function __construct(
        public GenerateDtoCommand $root,
        public ClassDto           $parent,
        public string             $name,
        public string             $path,
        mixed                     $value,
    )
    {
        $this->data[] = $value;
        $this->types[] = gettype($value);
    }

    public function addValue(mixed $value)
    {
        $this->data[] = $value;
        $this->types[] = gettype($value);
    }

    public function getType(): string
    {
        if (count($this->types) > 1) {
            return 'array';
        }
        $result = $type = $this->types[0];
        $nullInType = in_array('NULL', $this->types);
        if ($type == 'integer' || $type == 'double'){
            $result= 'int';
        }
        elseif ($type == "boolean"){
            $result= 'bool';
        }
        elseif ($type == "NULL"){
            $result= 'null';
        }
        if ($nullInType){
            $result .= '|null';
        }

        return $result;
    }

    public function equal($obj): bool
    {
        if ($obj instanceof PropertyDto) {
            return $this->path == $obj->path && $this->name == $obj->name;
        }
        return false;
    }

}
