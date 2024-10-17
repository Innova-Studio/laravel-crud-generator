<?php

namespace InnovaStudio\LaravelCrudGenerator\Generators;

use InnovaStudio\LaravelCrudGenerator\FileGenerator;
use Illuminate\Support\Str;

class FactoryGenerator extends FileGenerator
{
    protected string $attributes = '';
    protected string $afterMaking = '// TO DO';
    protected string $afterCreating = '// TO DO';

    public function setFileContent() : void
    {
        $this->addModelUrl();
        $this->setAttributes();
    }

    public function addModelUrl() : void
    {
        parent::addFileUseUrl( $this->entityData->modelUrl );
    }

    public function setAttributes() : void
    {
        $attributes = [];
        if( $this->entityData && property_exists( $this->entityData, 'attributes' ) )
        {
            foreach( $this->entityData->attributes as $attributeName => $attributeData )
            {
                $fakerType = $this->getFakerType( $attributeName, $attributeData );
                $attributes[] = "'{$attributeName}' => {$fakerType}";
            }
        }
        $this->attributes = empty( $attributes )? "// TO DO" : implode( ",\n\t\t\t", $attributes );
    }

    public function getFakerType( string $attributeName, object $attributeData ) : string
    {
        $integers = [ 'int', 'integer', 'bigInteger', 'unsignedBigInteger' ];
        $unique = property_exists( $attributeData, 'unique' ) && $attributeData->unique? '->unique()' : '';
        $default = property_exists( $attributeData, 'default' ) && $attributeData->default? ', ' . $attributeData->default : '';
        $nullable = property_exists( $attributeData, 'nullable' ) && $attributeData->nullable? "->optional( 0.9{$default} )" : '';
        $options = $unique . $nullable;
        if( property_exists( $attributeData, 'type' ) )
        {
            if( str_contains( $attributeName, 'slug' ) && in_array( $attributeData->type, [ 'string', 'text' ] ) )
                return "\$this->faker{$options}->slug( 5 )";
            if( str_contains( $attributeName, 'image' ) && in_array( $attributeData->type, [ 'string', 'text' ] ) )
                return "\$this->faker{$options}->imageUrl( 640, 480, 'people' )";
            if( $attributeData->type == 'string' && in_array( $attributeName, [ 'mail', 'email' ] ) )
                return "\$this->faker{$options}->email()";
            if( $attributeData->type == 'string' && in_array( $attributeName, [ 'name', 'fullname', 'full_name' ] ) )
                return "\$this->faker->firstName() . ' ' . \$this->faker->lastName()";
            if( $attributeData->type == 'string' && in_array( $attributeName, [ 'firstname', 'first_name' ] ) )
                return "\$this->faker{$options}->firstName()";
            if( $attributeData->type == 'string' && in_array( $attributeName, [ 'lastname', 'last_name' ] ) )
                return "\$this->faker{$options}->lastName()";
            if( $attributeData->type == 'string' && in_array( $attributeName, [ 'title' ] ) )
                return "\$this->faker{$options}->sentence( 6 )";
            if( in_array( $attributeData->type, [ 'string', 'text' ] ) && in_array( $attributeName, [ 'desc', 'description', 'caption', 'body', 'text', 'content', 'comment', 'summary'  ] ) )
                return "\$this->faker{$options}->text()";
            if( $attributeData->type == 'string' && in_array( $attributeName, [ 'address', 'fulladdress' ] ) )
                return "\$this->faker{$options}->address()";
            if( $attributeData->type == 'string' && in_array( $attributeName, [ 'postalcode', 'address_code' ] ) )
                return "\$this->faker{$options}->postcode()";
            if( $attributeData->type == 'string' && in_array( $attributeName, [ 'code' ] ) )
                return "\$this->faker{$nullable}->bothify('???-#####')";
            if( $attributeData->type == 'date' ) return "\$this->faker{$options}->date()";
            if( strtolower( $attributeData->type ) == 'datetime' ) return "\$this->faker{$options}->dateTime()";
            if( $attributeData->type == 'time' ) return "\$this->faker{$options}->time()";
            if( $attributeData->type == 'boolean' ) return "\$this->faker{$options}->boolean()";
            if( str_contains( $attributeName, '_id' ) && in_array( $attributeData->type, $integers ) )
            {
                if( $attributeName != 'parent_id' )
                {
                    $field = '$' . Str::camel( $this->entityName );
                    $relationClass = self::findRelationClass( $attributeName );
                    $this->afterMaking .= "\n\t\t\t{$field}->{$attributeName} = \\{$relationClass}::inRandomOrder()->first()->id;";
                }
                return $nullable? "null" : "1";
            }
            if( in_array( $attributeData->type, $integers ) ) return "\$this->faker{$options}->numberBetween( 1, 1000 )";
            if( in_array( $attributeData->type, [ 'float', 'decimal' ] ) ) return "\$this->faker{$options}->randomFloat( 2, 1, 100 )";
        }
        return '$this->faker->sentence(10)';
    }



    public function generateFileContent() : void
    {
        parent::generateFileContent();
        $this->fileContent = str_replace( '{{ attributes }}', $this->attributes, $this->fileContent );
        $this->fileContent = str_replace( '{{ model }}', $this->entityData->modelClassname, $this->fileContent );
        $this->fileContent = str_replace( '{{ var_name }}', Str::camel( $this->entityData->modelClassname ), $this->fileContent );
        $this->fileContent = str_replace( '{{ after_making }}', $this->afterMaking, $this->fileContent );
        $this->fileContent = str_replace( '{{ after_creating }}', $this->afterCreating, $this->fileContent );
    }
}
