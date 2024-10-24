<?php

namespace InnovaStudio\LaravelCrudGenerator;

use Illuminate\Support\Facades\File;
use InnovaStudio\LaravelCrudGenerator\Interfaces\FileGeneratorInterface;
use Illuminate\Support\Str;

abstract class FileGenerator implements FileGeneratorInterface
{
    protected string $entityName;
    protected string $entitySingularName;
    protected string $entityPluralName;
    protected string | null $entityModule = null;
    protected $entityData;
    protected string $filePath;
    protected string $fileName;
    protected string $fileUrl;
    protected array $fileUseUrls;
    protected string $fileExtends;
    protected string $fileInterfaces;
    protected string $fileTraits;
    protected string $fileContent;
    protected array $configurationOptions;
    protected string $fileType;
    protected $fileData;
    protected string $classname;
    protected string $classNamespace;
    protected bool $softDeletes;
    protected string | null $defaultNamespace;
    protected string $defaultFilePath;

    protected array $stubKeys = [ 'namespace', 'use', 'classname', 'extends', 'traits', 'implements' ];

    /**
     * Class constructor.
     *
     * @param string $entityName   The name of the entity.
     * @param $entityData   The data of the entity.
     */
    public function __construct( string $entityName, object $entityData = null )
    {
        $this->entityName = $entityName;
        $this->entityData = $entityData;
        $this->configurationOptions = require config_path( 'laravelCrudGenerator.php' );
        $this->setGeneratorType();
        $this->setGeneratorData();
        $this->setEntitySingularName();
        $this->setEntityPluralName();
        $this->setEntityModule();
        $this->setSoftDeletes();
        $this->setDefaultNamespace();
        $this->setDefaultFilePath();
        $this->setFilePath();
        $this->makePathDirectory();
        $this->setClassname();
        $this->setFileName();
        $this->setFileUrl();
        $this->setFileUseUrls();
        $this->setFileExtends();
        $this->setFileInterfaces();
        $this->setFileTraits();
        $this->setClassNamespace();
        $this->setFileContent();
    }

    public function setGeneratorType() : void
    {
        $this->fileType = strtolower( str_replace( [ 'InnovaStudio\\LaravelCrudGenerator\\Generators\\', 'Generator' ], '', get_class( $this ) ) );
    }

    public function setEntitySingularName() : void
    {
        $this->entitySingularName = Str::studly( $this->entityData && property_exists( $this->entityData, 'nameSingular' )? $this->entityData->nameSingular : $this->entityName );
    }

    public function setEntityPluralName() : void
    {
        $this->entityPluralName = Str::studly( $this->entityData && property_exists( $this->entityData, 'namePlural' )? $this->entityData->namePlural : Str::plural( $this->entitySingularName ) );
    }

    public function setSoftDeletes() : void
    {
        $this->softDeletes = $this->entityData && property_exists( $this->entityData, 'softDeletes' )? $this->entityData->softDeletes: $this->configurationOptions[ 'soft_deletes' ];
    }

    public function setEntityModule() : void
    {
        $this->entityModule = $this->entityData && property_exists( $this->entityData, 'module' )? $this->entityData->module: $this->configurationOptions[ 'module' ];
        $this->entityModule = Str::studly( $this->entityModule );
    }

    public function setDefaultNamespace() : void
    {
        $this->defaultNamespace = $this->configurationOptions[ $this->fileType ][ 'namespace' ];
        if( $this->entityModule )
            $this->defaultNamespace .= '\\' . $this->entityModule;
    }

    public function setDefaultFilePath() : void
    {
        $this->defaultFilePath = $this->configurationOptions[ $this->fileType ][ 'file_path' ];
        if( $this->entityModule && $this->fileType !== 'migration' )
            $this->defaultFilePath .= '/' . $this->entityModule;
    }

    public function setGeneratorData() : void
    {
        $fileType = $this->fileType;
        $this->fileData = isset( $this->entityData ) && property_exists( $this->entityData, $fileType )? $this->entityData->$fileType : null;
    }

    public function setFilePath() : void
    {
        $fallbackPath = $this->fileData && property_exists( $this->fileData, 'namespace' )?
                            $this->namespaceToFilepath( $this->fileData->namespace ):
                            $this->defaultFilePath;
        $this->filePath = $this->fileData && property_exists( $this->fileData, 'filePath' )? $this->fileData->filePath: $fallbackPath;
    }

    protected function makePathDirectory() : void
    {
        if( !File::exists( $this->filePath ) ) File::makeDirectory( $this->filePath, 0755, true );
    }

    public function setFileName() : void
    {
        $this->fileName = $this->classname . '.php';
    }

    public function setFileUrl() : void
    {
        $this->fileUrl = $this->filePath . '/' . $this->fileName;
    }

    public function setFileUseUrls() : void
    {
        $this->fileUseUrls = [];
        if( ( $this->fileData && property_exists( $this->fileData, 'extends' ) && strpos( $this->fileData->extends, '\\') > -1 ) || ( isset( $this->configurationOptions[ $this->fileType ][ 'extends' ] ) && $this->configurationOptions[ $this->fileType ][ 'extends' ] && strpos( $this->configurationOptions[ $this->fileType ][ 'extends' ], '\\') > -1 ) )
            $this->fileUseUrls[] = $this->fileData && property_exists( $this->fileData, 'extends' )? $this->fileData->extends : $this->configurationOptions[ $this->fileType ][ 'extends' ];
        $this->fileUseUrls = array_merge( $this->fileUseUrls, $this->fileData && property_exists( $this->fileData, 'interfaces' )? $this->fileData->interfaces : $this->configurationOptions[ $this->fileType ][ 'interfaces' ] );
        $this->fileUseUrls = array_merge( $this->fileUseUrls, $this->fileData && property_exists( $this->fileData, 'traits' )? $this->fileData->traits : $this->configurationOptions[ $this->fileType ][ 'traits' ] );
        $this->fileUseUrls = array_merge( $this->fileUseUrls, $this->fileData && property_exists( $this->fileData, 'use' )? $this->fileData->use : $this->configurationOptions[ $this->fileType ][ 'use' ] );
    }

    public function addFileUseUrl( string $url ) : void
    {
        $urlArray = explode( '\\', $url );
        $className = end( $urlArray );
        $as = $url . ' as ' . $urlArray[ count( $urlArray ) - 2 ] . $className;
        foreach( $this->fileUseUrls as $fileUrl )
        {
            if( $url == $fileUrl )
                return;
            $fileUrlArray = explode( '\\', $fileUrl );
            if( $className == end( $fileUrlArray ) || $className == $this->classname )
                $url = $as;
        }
        $this->fileUseUrls[] = $url;
    }

    public function setFileExtends() : void
    {
        $this->fileExtends = '';
        if( ( $this->fileData && property_exists( $this->fileData, 'extends' ) ) || ( isset( $this->configurationOptions[ $this->fileType ][ 'extends' ] ) && $this->configurationOptions[ $this->fileType ][ 'extends' ] ) )
        {
            $extendUrl = $this->fileData && property_exists( $this->fileData, 'extends' )? $this->fileData->extends : $this->configurationOptions[ $this->fileType ][ 'extends' ];
            $this->fileExtends = ' extends ' . self::getClassNameFromUrl( $extendUrl );
        }
    }

    public function setFileInterfaces() : void
    {
        $this->fileInterfaces = '';
        if( $this->fileData && property_exists( $this->fileData, 'interfaces' ) || ( isset( $this->configurationOptions[ $this->fileType ][ 'interfaces' ] ) && $this->configurationOptions[ $this->fileType ][ 'interfaces' ] ) )
        {
            $interfaces = $this->fileData && property_exists( $this->fileData, 'interfaces' )? $this->fileData->interfaces : $this->configurationOptions[ $this->fileType ][ 'interfaces' ];
            foreach( $interfaces as $key => $interfaceUrl )
            {
                $interfaces[ $key ] = self::getClassNameFromUrl( $interfaceUrl );
            }
            $this->fileInterfaces = ' implements ' . implode( ', ', $interfaces );
        }
    }

    public function setFileTraits() : void
    {
        $this->fileTraits = '';
        if( $this->fileData && property_exists( $this->fileData, 'traits' ) || ( isset( $this->configurationOptions[ $this->fileType ][ 'traits' ] ) && $this->configurationOptions[ $this->fileType ][ 'traits' ] ) )
        {
            $traits = $this->fileData && property_exists( $this->fileData, 'traits' )? $this->fileData->traits : $this->configurationOptions[ $this->fileType ][ 'traits' ];
            foreach( $traits as $key => $traitsUrl )
            {
                $traits[ $key ] = self::getClassNameFromUrl( $traitsUrl );
            }
            $this->fileTraits = "\tuse " . implode( ', ', $traits ) . ";\n\n";
        }
    }

    public function setClassname() : void
    {
        $classname = $this->fileData && property_exists( $this->fileData, 'classname' )? $this->fileData->classname : $this->entityName . ucfirst( $this->fileType );
        $this->classname = $classname ?? '';
    }

    public function findRelationClass( string $relationName ) : ? string
    {
        if( str_contains( $relationName, '_' ) ) $relationName = Str::before( $relationName, '_id' );
        $relationName = Str::studly( $relationName );
        if( $this->entityData && property_exists( $this->entityData, 'relations' ) )
        {
            foreach( $this->entityData->relations as $relationType )
            {
                foreach( $relationType as $relationEntity => $relationData )
                {
                    if( $relationEntity == $relationName ) return $relationData->related;
                }
            }
        }
        return $relationName;
    }

    public function getRelatedClass( string $modelRelation, object $relationData, bool $withAs = true ) : string
    {
        return $this->getClassNameFromUrl( $relationData->related ?? $modelRelation, $withAs );
    }

    public function generateFile() : void
    {
        $this->generateFileContent();
        $this->createFile();
    }

    public function createFile() : bool
    {
        if( $this->fileShouldBeCreated() )
        {
            $this->generateFileContent();
            File::put( $this->fileUrl, $this->fileContent );
            if( $this->fileType === 'resource' )
            {
                $this->fileType = 'collection';
                $this->classname = str_replace( 'Resource', 'Collection', $this->classname );
                $fileName = Str::afterLast( $this->fileUrl, '/' );
                $newFileName = str_replace( 'Resource', 'Collection', $fileName );
                $this->fileUrl = str_replace( $fileName, $newFileName, $this->fileUrl );
                $this->generateFileContent();
                $this->fileContent = str_replace( 'JsonResource', 'ResourceCollection', $this->fileContent );
                $this->fileContent = str_replace( 'resource into', 'resource collection into', $this->fileContent );
                File::put( $this->fileUrl, $this->fileContent );
            }
            return true;
        }
        return false;
    }

    public function setClassNamespace() : void
    {
        if( $this->fileType === 'migration' ) $this->classNamespace = '';
        if( in_array( $this->fileType, [ 'routes', 'migration' ] ) ) return;

        $fallbackNamespace = $this->fileData && property_exists( $this->fileData, 'filePath' )?
            $this->filepathToNamespace( $this->fileData->filePath ):
            $this->defaultNamespace;
        $classNamespace = $this->fileData && property_exists( $this->fileData, 'namespace' )? $this->fileData->namespace : $fallbackNamespace;
        $this->classNamespace = $classNamespace ?? '';
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

    public function fileShouldBeCreated() : bool
    {
        $fileCantBeRewrited = $this->fileCantBeRewrited();
        $entityCantBeRewrited = $this->entityCantBeRewrited();
        $fileCanBeRewrited = !( $fileCantBeRewrited || $entityCantBeRewrited );
        $fileNotExist = $this->fileNotExists( $fileCanBeRewrited );
        $shouldBeCreated = $fileNotExist || $fileCanBeRewrited;
        return $shouldBeCreated;
    }

    public function fileCanBeRewrited() : bool
    {
        return !( $this->entityCantBeRewrited() || $this->fileCantBeRewrited() );
    }

    public function entityCantBeRewrited() : bool
    {
        return ( $this->entityData && property_exists( $this->entityData, 'rewrite' ) && $this->entityData->rewrite === false ) || ( $this->entityData && $this->configurationOptions[ 'rewrite' ] === false );
    }

    public function fileCantBeRewrited() : bool
    {
        return ( $this->fileData && property_exists( $this->fileData, 'rewrite' ) && $this->fileData->rewrite === false ) || ( !$this->fileData && $this->configurationOptions[ $this->fileType ][ 'rewrite' ] === false );
    }

    public function fileNotExists( bool $fileCanBeRewrited ) : bool
    {
        if( $this->fileType != 'migration' ) return !File::exists( $this->fileUrl );

        $fileUrl = explode( '/', $this->fileUrl );
        $fileUrl = implode( '/', array_slice( $fileUrl, 0, count( $fileUrl ) - 1 ) );
        $migrationNameWithoutTimestamp = explode( '_', $this->fileName, 5 )[4];
        $migrationFiles = File::glob( database_path( 'migrations/*_*.php' ) );
        foreach( $migrationFiles as $migrationFile )
        {
            $migrationFileName = Str::afterLast( Str::afterLast( $migrationFile, '\\' ), '/' );

            if( Str::contains( explode( '_', $migrationFileName, 5 )[4], $migrationNameWithoutTimestamp ) )
            {
                if( $fileCanBeRewrited )
                {
                    $this->fileName = $migrationFileName;
                    $this->fileUrl = $fileUrl . '/' . $this->fileName;
                }
                return false;
            }
        }
        return true;
    }

    public static function getClassNameFromUrl( $classUrl, bool $withAs = true ) : string
    {
        $className = explode( '\\', $classUrl );
        $className = $className[ count( $className ) - 1 ];
        $className = explode( ' as ', $className );
        return $withAs && isset( $className[ 1 ] )? $className[ 1 ] : $className[ 0 ];
    }

    public static function isCannonicalMethod( string $method ) : bool
    {
        $methodToGenerateContent = 'generate' . $method . 'MethodContent';
        return method_exists( get_called_class(), $methodToGenerateContent );
    }

    public function generateFileContent() : void
    {
        $this->sanitizeUseUrls();
        $template = File::get( __DIR__ . '/Stubs/template.stub' );
        $this->fileContent = str_replace( '{{ file_stub }}', File::get( __DIR__ . "/Stubs/{$this->fileType}.stub" ), $template );
        $this->fileContent = str_replace( '{{ namespace }}', $this->classNamespace, $this->fileContent );
        $this->fileContent = str_replace( '{{ use }}', empty( $this->fileUseUrls )? '' : 'use ' . implode( ";\nuse ", $this->fileUseUrls ) . ";\n\n", $this->fileContent );
        $this->fileContent = str_replace( '{{ classname }}', $this->classname, $this->fileContent );
        $this->fileContent = str_replace( '{{ extends }}', $this->fileExtends, $this->fileContent );
        $this->fileContent = str_replace( '{{ interfaces }}', $this->fileInterfaces, $this->fileContent );
        $this->fileContent = str_replace( '{{ traits }}', $this->fileTraits, $this->fileContent );
    }

    public function sanitizeUseUrls()
    {
        foreach( $this->fileUseUrls as $key => $url )
        {
            $url = str_replace( $this->classNamespace . '\\', '', $url );
            if( strpos( $url, '\\' ) === false )
            {
                unset( $this->fileUseUrls[ $key ] );
            }
        }
        sort( $this->fileUseUrls );
    }

    public static function generateMethodTemplate( $methodName, $methodArguments = '', $methodReturnType = 'void', $methodIsStatic = false, $methodScope = 'public' ) : string
    {
        $methodData = [
            'method_name' => $methodName,
            'method_arguments' => $methodArguments,
            'method_return_type' => $methodReturnType? ' : ' . $methodReturnType : '',
            'method_static' => $methodIsStatic? ' static' : '',
            'method_scope' => $methodScope
        ];
        return self::generateFromTemplate( 'method', $methodData );
    }

    public static function generateFromTemplate( string $template, array $templateData ) : string
    {
        $template = File::get( __DIR__ . "/Stubs/$template.stub" );
        foreach( $templateData as $variable => $value )
        {
            $template = str_replace( "{{ $variable }}", $value, $template );
        }
        $template = str_replace( "\\t", "\t", $template );
        $template = str_replace( "\\n", "\n", $template );
        $template = str_replace( "(  )", "()", $template );
        return $template;
    }

}
