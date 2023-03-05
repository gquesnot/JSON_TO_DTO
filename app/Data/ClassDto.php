<?php

namespace App\Data;

use App\Console\Commands\GenerateDtoCommand;
use App\Helpers\PathHelper;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ClassDto extends \Spatie\LaravelData\Data
{

    public array $properties = [];

    public function __construct(
        public GenerateDtoCommand $root,
        public ?ClassDto          $parent,
        public string             $name,
        public string             $path,
        public array              $data,
    )
    {
        # check array or array key
        $this->parseArray($name, $path, $data, true);
    }


    private function parseArray($name, $path, $array, $first = false)
    {
        if (Arr::isAssoc($array)) {
            if ($first) {
                $this->parseObject($name, $path, $array);
            } else {
                $this->addObject($name, $path, $array, $first);
            }

        } else {

            foreach ($array as $value) {
                if (is_array($value)) {
                    $this->parseArray($name, $path, $value);
                } else {
                    $this->addProperty($name, $path, $value);
                }
            }
        }
    }

    private function addObject($name, $path, $data, $first)
    {
        $object = $this->root->addObject($this, $name, $path, $data, $first);
        if (!in_array($name, $this->properties)) {
            $this->properties[$name] = $object;
        }
    }

    public function getType(): string
    {
        return Str::studly($this->name) . "Data";
    }


    private function addProperty($name, $path, $data)
    {
        $property = $this->root->addProperty($this, $name, $path, $data);
        if (!in_array($name, $this->properties)) {
            $this->properties[$name] = $property;
        }
    }


    private function parseObject($name, $path, $object)
    {
        foreach ($object as $key => $value) {
            if (is_array($value)) {
                $this->parseArray($key, PathHelper::add($path, $key), $value);
            } else {
                $this->addProperty($key, PathHelper::add($path, $key), $value);
            }
        }
    }

    public function equal($obj): bool
    {
        if ($obj instanceof ClassDto) {
            return $this->path == $obj->path && $this->name == $obj->name;
        }
        return false;
    }
}
