<?php

namespace InnovaStudio\LaravelCrudGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class LaravelCrudGenerator
{
    private object $crudData;
    private array $configurationOptions;
    private Command $command;

    public function __construct( $command, $filePath = null )
    {
        $this->command = $command;
        $this->configurationOptions = require config_path( 'laravelCrudGenerator.php' );
        $this->crudData = json_decode( file_get_contents( $filePath?  $filePath : $this->configurationOptions[ 'default_file_path' ] ) );
    }

    public function generateFiles()
    {
        $this->command->newLine();
        foreach( get_object_vars( $this->crudData->entities ) as $entityName => $entityData )
        {
            $entityData = $this->setEntityData( $entityName, $entityData );
            $this->command->line( "<options=bold;fg=bright-yellow;>⚡</><options=bold;fg=bright-magenta;> CRUD generation for {$entityName}</>" );
            foreach( $entityData->files as $file )
            {
                $this->generateFile( $file, $entityName, $entityData );
            }
            $this->command->line( "<options=bold;fg=bright-white;>└─></> <options=bold;fg=bright-green;>✔ </><options=bold;fg=bright-cyan;> {$entityName} has been generated successfully</>" );
            $this->command->newLine();
        }
    }

    public function generateFile( string $fileType, string $entityName, object $entityData = null )
    {
        $fileType = ucfirst( $fileType );
        $class = 'InnovaStudio\\LaravelCrudGenerator\\Generators\\' . $fileType . 'Generator';
        $generator = new $class( $entityName, $entityData );
        $generator->createFile()?
            $this->command->line( "<options=bold;fg=bright-white;>├─></> <options=bold;fg=bright-green;>✔ </><options=bold;fg=white;> {$fileType}</>" ):
            $this->command->line( "<options=bold;fg=bright-white;>├─></> <options=bold;fg=bright-red;>❌</><options=bold;fg=red;> {$fileType}</>" );
    }

    public function setEntityData( string $entityName, object $entityData )
    {
        $entityData = !empty( (array) $entityData )? $entityData : null;
        if( !$entityData ) $entityData = new \stdClass();
        $entityData->files = $entityData && property_exists( $entityData, 'files' )? $entityData->files : $this->configurationOptions[ 'files' ];

        foreach( [ 'model', 'controller', 'service' ] as $globalEntity )
            $entityData = $this->setGlobalEntity( $globalEntity, $entityName, $entityData );
        return $entityData;
    }

    public function setGlobalEntity( string $globalEntity, string $entityName, object $entityData )
    {
        $entityIsSet = property_exists( $entityData, $globalEntity );
        $classnameAttribute = $globalEntity."Classname";
        $filePathAttribute = $globalEntity."FilePath";
        $namespaceAttribute = $globalEntity."Namespace";
        $urlAttribute = $globalEntity."Url";
        $defaultClassname = $globalEntity == 'model'? '' : ucfirst( $globalEntity );


        $module = $entityData && property_exists( $entityData, 'module' )? Str::studly( $entityData->module ) : null;
        $entityData->$classnameAttribute = $entityIsSet && property_exists( $entityData->$globalEntity, 'classname' )? $entityData->$globalEntity->classname : $entityName . $defaultClassname;
        $entityData->$filePathAttribute = $entityIsSet && property_exists( $entityData->$globalEntity, 'filePath' )? $entityData->$globalEntity->filePath : ( $entityIsSet && property_exists( $entityData->$globalEntity, 'namespace' )? $this->namespaceToFilepath( $entityData->$globalEntity->namespace ) : $this->configurationOptions[ $globalEntity ][ 'file_path' ] .( $module ? '/' . $module : '' ) );
        $entityData->$namespaceAttribute = $entityIsSet && property_exists( $entityData->$globalEntity, 'namespace' )?
            $entityData->$globalEntity->namespace:
            (
                $entityIsSet && property_exists( $entityData->$globalEntity, 'filePath' )?
                    $this->filepathToNamespace( $entityData->$globalEntity->filePath ):
                    $this->configurationOptions[ $globalEntity ][ 'namespace' ] . ( $module ? '\\' . $module : '' )
            );
        $entityData->$urlAttribute = $entityData->$namespaceAttribute . '\\' . $entityData->$classnameAttribute;
        return $entityData;
    }

    public function filepathToNamespace( string $filepath )
    {
        $relativePath = str_replace( base_path() . '/', '', $filepath );
        $namespace = implode( '\\', array_map( function( $value ) { return Str::studly( $value ); }, explode( '/', $relativePath ) ) );
        if ( strpos( $namespace, 'app\\' ) === 0 )
            $namespace = 'App' . substr( $namespace, 3 );
        return $namespace;
    }

    public function namespaceToFilepath( string $namespace )
    {
        $filepath = implode( '/', array_map( function( $value ) { return Str::studly( $value ); }, explode( '\\', $namespace ) ) );
        if ( strpos( $filepath, 'App/' ) === 0 )
            $filepath = 'app' . substr( $filepath, 3 );
        return $filepath;
    }
}
