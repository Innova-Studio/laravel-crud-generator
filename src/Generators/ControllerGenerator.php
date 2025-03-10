<?php

namespace InnovaStudio\LaravelCrudGenerator\Generators;

use stdClass;
use Illuminate\Support\Str;
use InnovaStudio\LaravelCrudGenerator\FileGenerator;

class ControllerGenerator extends FileGenerator
{
    protected array $controllerMethods;
    protected array $methodsContent;
    protected string $defaultMethodResponse;
    protected string $entityService;
    protected string $entityServiceVar;
    protected string $entityResource;
    protected string $entityCollection;
    protected string $entityVar;
    protected string $serviceCall;
    protected bool $staticServiceMethods;

    public function setFileContent() : void
    {
        $this->setControllerMethods();
        $this->setDefaultMethodResponse();
        $this->setEntityService();
        $this->setEntityResource();
        $this->setEntityVar();
        $this->setMethods();
        $this->addResourceFilesToUseUrls();
    }

    public function setControllerMethods() : void
    {
        $this->controllerMethods = $this->fileData && property_exists( $this->fileData, 'methods' )? $this->fileData->methods : $this->configurationOptions[ $this->fileType ][ 'methods' ];
    }

    public function setDefaultMethodResponse() : void
    {
        $this->defaultMethodResponse = $this->fileData && property_exists( $this->fileData, 'response' )? $this->fileData->response : $this->configurationOptions[ $this->fileType ][ 'response' ];
    }

    public function generateFileContent() : void
    {
        parent::generateFileContent();
        $this->fileContent = str_replace( '{{ methods }}', implode( "\n", $this->methodsContent ), $this->fileContent );
    }

    public function setEntityVar() : void
    {
        $this->entityVar = "\$" . strtolower( $this->entityName );
    }

    public function setEntityService() : void
    {
        $this->entityService = $this->entityData->serviceClassname;
        $this->entityServiceVar = Str::camel( $this->entityService );
        $this->fileUseUrls[] = $this->entityData->serviceUrl;

        $this->staticServiceMethods = property_exists( $this->entityData, 'service' ) && property_exists( $this->entityData->service, 'static_methods' )? $this->entityData->service->static_methods : $this->configurationOptions[ 'service' ][ 'static_methods' ];
        $this->serviceCall = $this->staticServiceMethods? "{$this->entityService}::" : "\$this->{$this->entityServiceVar}->";
    }

    public function setEntityResource() : void
    {
        $this->entityResource = $this->entityName . 'Resource';
        $this->entityCollection = $this->entityName . 'Collection';
    }

    public function setMethods() : void
    {
        if( !$this->staticServiceMethods )
        {
            $constructMethodArguments = $this->generateConstructMethodArguments();
            $methodBaseTemplate = parent::generateMethodTemplate( '__construct', $constructMethodArguments, null );
            $this->methodsContent[ '__construct' ] = str_replace( '{{ method_content }}', '', $methodBaseTemplate );
        }

        foreach( $this->controllerMethods as $method )
        {
            if( self::isCannonicalMethod( $method ) )
            {
                $methodToGenerateContent = 'generate' . $method . 'MethodContent';
                $methodContent = $this->$methodToGenerateContent();
                $methodToGenerateArguments = 'generate' . $method . 'MethodArguments';
                $methodArguments = method_exists( $this, $methodToGenerateArguments )? $this->$methodToGenerateArguments() : null;
            }else{
                $methodContent = $this->generateDefaultMethodContent();
                $methodArguments = $this->generateDefaultMethodArguments( $method ); //"Request \$request";
                if( !in_array( "Illuminate\\Http\\Request", $this->fileUseUrls ) ) $this->fileUseUrls[] = "Illuminate\\Http\\Request";
            }
            $methodBaseTemplate = parent::generateMethodTemplate( $method, $methodArguments, $this->defaultMethodResponse );
            $this->methodsContent[ $method ] = str_replace( '{{ method_content }}', $methodContent, $methodBaseTemplate );
        }
    }

    public function generateConstructMethodArguments() : string
    {
        return "private {$this->entityService} \${$this->entityServiceVar}";
    }

    public function getMethodRequestFile( string $method ) : string
    {
        return Str::studly( $method ) . 'Request';
    }

    public function addRequestFileToUseUrls( string $requestFile ) : void
    {
        $this->fileUseUrls[] = $requestFile;
    }

    public function addResourceFilesToUseUrls() : void
    {
        parent::addFileUseUrl( $this->getResourceResponseNamespace() );
        parent::addFileUseUrl( $this->getResourceCollectionNamespace() );
    }

    public function getResourceNamespace() : string
    {
        return $this->entityData && property_exists( $this->entityData, 'resource' ) && property_exists( $this->entityData->resource, 'namespace' )? $this->entityData->resource->namespace : $this->configurationOptions[ 'resource' ][ 'namespace' ] . ( $this->entityModule ? '\\' . $this->entityModule : '' );
    }

    public function getResourceResponseNamespace() : string
    {
        $entityName = Str::studly( $this->entityName );
        return $this->getResourceNamespace() . "\\{$entityName}\\{$this->entityResource}";
    }

    public function getResourceCollectionNamespace() : string
    {
        $entityName = Str::studly( $this->entityName );
        return $this->getResourceNamespace() . "\\{$entityName}\\{$this->entityCollection}";
    }

    public function generateRequestFile( string $requestFile ) : string
    {
        $requestFile = Str::studly( $requestFile );
        $objectData = new stdClass();
        $objectData->files = [ 'request' ];
        $objectData->attributes = $this->entityData && property_exists( $this->entityData, 'attributes' )? $this->entityData->attributes : [];
        $requestDataExists = $this->entityData && property_exists( $this->entityData, 'request' );
        $objectData->request = $requestDataExists? (array) $this->entityData->request : [];
        $objectData->request[ 'filePath' ] = $requestDataExists && property_exists( $this->entityData->request, 'filePath' )?
            $this->entityData->request->filePath:
            ( property_exists( $this->entityData, 'request' ) && property_exists( $this->entityData->request, 'namespace' )?
                $this->namespaceToFilepath( $this->entityData->request->namespace ):
                $this->configurationOptions[ 'request' ][ 'file_path' ] . ( $this->entityModule ? '/' . $this->entityModule : '' ) );

        $objectData->request[ 'namespace' ] = $requestDataExists && property_exists( $this->entityData->request, 'namespace' )?
            $this->entityData->request->namespace:
            ( property_exists( $this->entityData, 'request' ) && property_exists( $this->entityData->request, 'filePath' )?
                $this->filepathToNamespace( $this->entityData->request->filePath ):
                $this->configurationOptions[ 'request' ][ 'namespace' ] . ( $this->entityModule ? '\\' . $this->entityModule : '' ) );
        $objectData->request[ 'filePath' ] .= '/' . Str::studly( $this->entityName );
        $objectData->request[ 'namespace' ] .= '\\' . Str::studly( $this->entityName );
        $objectData->request[ 'table' ] = $this->entityData && property_exists( $this->entityData, 'model' ) && property_exists( $this->entityData->model, 'table' )? $this->entityData->model->table : Str::snake( $this->entityPluralName );
        $generator = new RequestGenerator( $requestFile, json_decode( json_encode( $objectData ) ) );
        $generator->createFile();
        return $objectData->request[ 'namespace' ] . '\\' . $this->getMethodRequestFile( $requestFile );
    }

    public function generateDefaultMethodContent() : string
    {
        return "// TO DO
        return response()->json( [
            'data' => []
        ] );";
    }

    public function generateDefaultMethodArguments( $method ) : string
    {
        $requestFile = $this->generateRequestFile( $method );
        $this->addRequestFileToUseUrls( $requestFile );
        return Str::afterLast( $requestFile, '\\' ) . " \$request";
    }

    public function generateStoreMethodContent() : string
    {
        return "{$this->entityVar} = {$this->serviceCall}store( \$request->validated() );
        return response()->json( [
            'data' => {$this->entityResource}::make( {$this->entityVar} ),
        ], 201 );";
    }

    public function generateStoreMethodArguments() : string
    {
        $requestFile = $this->generateRequestFile( 'store' );
        $this->addRequestFileToUseUrls( $requestFile );
        return Str::afterLast( $requestFile, '\\' ) . " \$request";
    }

    public function generateUpdateMethodContent() : string
    {
        return "{$this->entityVar} = {$this->serviceCall}update( \$request->validated(), {$this->entityVar}Id );
        return {$this->entityVar} ?

            response()->json( [
                'data' => {$this->entityResource}::make( {$this->entityVar} ),
            ] ):

            response()->json( [
                'error' => '{$this->entityName} with id ' . {$this->entityVar}Id . ' not found',
            ], 404 );";
    }

    public function generateUpdateMethodArguments() : string
    {
        $requestFile = $this->generateRequestFile( 'update' );
        $this->addRequestFileToUseUrls( $requestFile );
        return Str::afterLast( $requestFile, '\\' ) . " \$request, int {$this->entityVar}Id";
    }

    public function generateDeleteMethodContent() : string
    {
        return "{$this->entityVar} = {$this->serviceCall}delete( {$this->entityVar}Id );
        return {$this->entityVar} ?

            response()->json( [
                'data' => {$this->entityResource}::make( {$this->entityVar} ),
            ], 200 ):

            response()->json( [
                'error' => '{$this->entityName} with id ' . {$this->entityVar}Id . ' not found',
            ], 404 );";
    }

    public function generateDeleteMethodArguments() : string
    {
        return "int {$this->entityVar}Id";
    }

    public function generateShowMethodContent() : string
    {
        return "{$this->entityVar} = {$this->serviceCall}fetch( {$this->entityVar}Id );
        return {$this->entityVar} ?

        response()->json( [
            'data' => {$this->entityResource}::make( {$this->entityVar} ),
        ] ):

        response()->json( [
            'error' => '{$this->entityName} with id ' . {$this->entityVar}Id . ' not found',
        ], 404 );";
    }

    public function generateFetchMethodContent() : string
    {
        return $this->generateShowMethodContent();
    }

    public function generateShowMethodArguments() : string
    {
        return "int {$this->entityVar}Id";
    }

    public function generateFetchMethodArguments() : string
    {
        return $this->generateShowMethodArguments();
    }

    public function generateListMethodContent() : string
    {
        $listVar = Str::plural( $this->entityVar );
        return "{$listVar} = {$this->serviceCall}list( \$request->validated() );
        return response()->json( [
            'data' => new {$this->entityCollection}( {$listVar} ),
        ] );";
    }

    public function generateListMethodArguments() : string
    {
        $requestFile = $this->generateRequestFile( 'list' );
        $this->addRequestFileToUseUrls( $requestFile );
        return Str::afterLast( $requestFile, '\\' ) . " \$request";
    }
}
