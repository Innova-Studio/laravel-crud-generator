<?php

namespace InnovaStudio\LaravelCrudGenerator\Generators;

use InnovaStudio\LaravelCrudGenerator\FileGenerator;
use Illuminate\Support\Str;

class RequestGenerator extends FileGenerator
{
    protected array $rules = [];
    protected string $requestType;

    public function setFileContent() : void
    {
        $this->setRequestType();
        $this->setRules();
    }

    public function setRequestType() : void
    {
        $this->requestType = in_array( Str::lower( $this->entityName ), [ 'store', 'update', 'list' ] )?
            $this->entityName:
            'default';
    }

    public function generateFileContent() : void
    {
        parent::generateFileContent();
        $rules = implode( "", $this->rules );
        $rules = $rules? $rules . "\n\t\t" : "";
        $this->fileContent = str_replace( '{{ rules }}', $rules, $this->fileContent );
    }

    public function setRules() : void
    {
        $methodToGenerateRules = 'generate' . Str::studly( $this->requestType ) . 'Rules';
        if( !$this->entityData || !property_exists( $this->entityData, 'attributes' ) || !method_exists( $this, $methodToGenerateRules ) ) return;
        $this->$methodToGenerateRules();
    }

    public function generateStoreRules() : void
    {
        foreach( $this->entityData->attributes as $attributeName => $attributeData )
        {
            $type = $this->formatRuleType( $attributeData->type );
            $nullable = property_exists( $attributeData, 'nullable' )? " 'nullable'," : 'required';
            $type = property_exists( $attributeData, 'type' )? " '$type'," : '';
            $unique = property_exists( $attributeData, 'unique' ) && $attributeData->unique? " 'unique:{$this->entityData->request->table}'," : '';
            $max = property_exists( $attributeData, 'max' )? " 'max:$attributeData->max'," : '';
            $min = property_exists( $attributeData, 'min' )? " 'min:$attributeData->min'," : '';
            $this->rules[] = str_replace( ',]', ' ]', "\n\t\t\t'$attributeName' => [" . $nullable . $type . $unique . $max . $min . "]," );
        }
    }

    public function generateUpdateRules() : void
    {
        foreach( $this->entityData->attributes as $attributeName => $attributeData )
        {
            $type = $this->formatRuleType( $attributeData->type );
            $nullable = property_exists( $attributeData, 'nullable' )? " 'nullable'," : 'required';
            $type = property_exists( $attributeData, 'type' )? " '$type'," : '';
            $unique = property_exists( $attributeData, 'unique' ) && $attributeData->unique? " 'unique:{$this->entityData->request->table},{$attributeName},' . \$this->route('id')," : '';
            $max = property_exists( $attributeData, 'max' )? " 'max:$attributeData->max'," : '';
            $min = property_exists( $attributeData, 'min' )? " 'min:$attributeData->min'," : '';
            $this->rules[] = str_replace( ',]', ' ]', "\n\t\t\t'$attributeName' => [" . $nullable . $type . $unique . $max . $min . "]," );
        }
    }

    public function generateListRules() : void
    {
        foreach( $this->entityData->attributes as $attributeName => $attributeData )
        {
            $type = $this->formatRuleType( $attributeData, );
            $nullable = property_exists( $attributeData, 'nullable' )? " 'nullable'," : 'required';
            $type = property_exists( $attributeData, 'type' )? " '$type'," : '';
            $max = property_exists( $attributeData, 'max' )? " 'max:$attributeData->max'," : '';
            $min = property_exists( $attributeData, 'min' )? " 'min:$attributeData->min'," : '';
            $this->rules[] = str_replace( ',]', ' ]', "\n\t\t\t'$attributeName' => [" . $nullable . $type . $max . $min . "]," );
        }
    }

    public function formatRuleType( $attributeData = null )
    {
        switch( Str::lower( $attributeData->type ) )
        {
            case 'text': return 'string';
            case 'datetime': return 'date';
            case 'biginteger': return 'integer';
            case 'unsignedbiginteger': return 'integer';
            case 'decimal':
                return property_exists( $attributeData, 'parameter2' )? "decimal:" . $attributeData->parameter2 : "decimal:2";
            default: return $attributeData->type;
        }
    }
}
