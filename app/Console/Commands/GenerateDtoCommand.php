<?php

namespace App\Console\Commands;

use App\Data\ClassDto;
use App\Data\PropertyDto;
use App\Helpers\PathHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GenerateDtoCommand extends Command
{
    protected $signature = 'generate:dto {name} {json_path} {--f|force}';

    protected $description = 'Generate DTO from JSON to App\\Data folder';

    public array $children = [];
    public ClassDto $root;

    public function handle()
    {
        $name = $this->argument('name');
        $json_path = $this->argument('json_path');
        $force = $this->option('force');

        $this->info("Generating DTO: $name");

        $json_data = file_get_contents($json_path);
        $data = json_decode($json_data, true);

        $this->root = new ClassDto($this, null, $name, $name, $data);
        # check class with same properties
        while ($this->clearDuplicate()) {}
        $this->printChildren();
        $this->generateDtos($name, $force);

    }

    private function generateDtos($name, $force)
    {
        $this->info("Generating DTOs");
        $path = app_path('Data/');
        if (!is_dir($path)) {
            mkdir($path);
        }
        if (!is_dir($path . $name) && count($this->children) > 0) {
            mkdir($path . $name);
        }
        $this->generateDto($this->root, $name, $force, true);

        foreach ($this->children as $child) {
            if (!($child instanceof ClassDto)) {
                continue;
            }
            $this->generateDto($child, $name, $force);
        }
    }

    private function generateDto(ClassDto $class, string $name = null, bool $force = false,bool $is_root = false)
    {

        $path = app_path('Data/' . (!$is_root ? $name . '/' : '') . $class->getType() . '.php');
        if (file_exists($path) && !$force) {
            $this->info("File exists: $path");
            return;
        }
        $this->info("Generating DTO: $path");
        $content = $this->generateDtoContent($class, $name, $is_root);
        file_put_contents($path, $content);
    }

    private function generateDtoContent(ClassDto $class,string $name, bool $is_root=false): string
    {
        $content = "<?php" . PHP_EOL . PHP_EOL;
        $content .= "namespace App\\Data" . (!$is_root ? '\\' . $name : '') . ";" . PHP_EOL . PHP_EOL;

        $found_import = false;
        foreach ($class->properties as $property){
            if($property instanceof ClassDto){
                $found_import = true;
                $content .= "use App\\Data\\$name\\". $property->getType() . ";" . PHP_EOL;
            }
        }
        if($found_import){
            $content .= PHP_EOL;
        }

        $content .= "class " . $class->getType() . " extends \\Spatie\\LaravelData\\Data" . PHP_EOL;
        $content .= "{" . PHP_EOL;

        $content.= "    public function __construct(" . PHP_EOL;
        foreach ($class->properties as $key => $property) {
            $content .= "        public " . $property->getType() . " $" . $key . "," . PHP_EOL;
        }
        $content .= "    )" . PHP_EOL;
        $content .= "    {" . PHP_EOL;

        $content .= "}" . PHP_EOL;
        $content .= PHP_EOL;
        $content.="}" . PHP_EOL;
        return $content;
    }

    private function clearDuplicate(): bool
    {
        foreach ($this->children as $child) {
            if (!($child instanceof ClassDto)) {
                continue;
            }
            $founds = [];
            foreach ($this->children as $child2) {
                if (!($child2 instanceof ClassDto)) {
                    continue;
                }
                if (
                    $child->path != $child2->path
                    && PathHelper::before($child->path) == PathHelper::before($child2->path)
                    && array_keys($child->properties) == array_keys($child2->properties)
                ) {
                    $founds[] = $child2;
                }
            }
            if ($founds) {
                $this->info('Found duplicate classes: ' . $child->name . " - " . $child->path);
                foreach ($founds as $found) {
                    $this->info('   - ' . $found->path);
                }
                $this->info('Properties: ');
                foreach ($child->properties as $property) {
                    $this->info('   - ' . $property->name);
                }

                $last_path = PathHelper::before($child->path);
                if (Str::endsWith($last_path, 's')) {
                    $new_class_name = Str::beforeLast($last_path, 's');
                } else {
                    $new_class_name = $this->ask('Enter new class name', $last_path);
                }
                # clear found and properties
                foreach ($child->properties as $property) {
                    $property->path = PathHelper::replace($property->path, $child->name, $new_class_name);
                }
                $child->path = PathHelper::replace($child->path, $child->name, $new_class_name);
                $child->name = $new_class_name;
                foreach ($founds as $found) {
                    $this->children = Arr::where($this->children, function ($value, $key) use ($found) {
                        foreach ($found->properties as $property) {
                            if ($property->equal($value)) {
                                return false;
                            }
                        }
                        return !$found->equal($value);
                    });

                    foreach ($found->parent->properties as $property) {
                        if ($property->equal($found)) {
                            $found->parent->properties[$property->name] = $child;
                        }
                    }
                }
                return false;
            }
        }
        return true;
    }

    private function iterChildrenClass(): array
    {
        return Arr::where($this->children, function ($value, $key) {
            return $value instanceof ClassDto;
        });
    }


    private function printChildren()
    {
        $this->info('----------------------------');
        foreach ($this->iterChildrenClass() as $child) {
            $this->info("Class " . $child->name . ' ' . $child->path);
            foreach ($child->properties as $key => $property) {
                $property_string = $key . ' ' . $property->path;
                if ($property instanceof ClassDto) {
                    $property_string .= ' Class ' . $property->name;
                } else {
                    $property_string .= ' ' . $property->getType();
                }
                $this->info("   - " . $property_string);
            }
        }
    }


    public function addObject(ClassDto $parent, $name, $path, $data, $first): ClassDto
    {
        $object = Arr::first($this->children, function ($value, $key) use ($path) {
            return $value->path == $path && $value instanceof ClassDto;
        });

        if (!$object) {
            if (!$first) {
                $name = Str::singular($name);
            }
            $object = new ClassDto($this, $parent, $name, $path, $data);
            $this->children[] = $object;
        }
        return $object;
    }

    public function addProperty(ClassDto $parent, $name, $path, $data): PropertyDto
    {
        $property = Arr::first($this->children, function ($value, $key) use ($path) {
            return $value->path == $path && $value instanceof PropertyDto;
        });

        if ($property) {
            $property->addValue($data);
        } else {
            $property = new PropertyDto($this, $parent, $name, $path, $data);
            $this->children[] = $property;
        }
        return $property;
    }

}
