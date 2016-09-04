<?php

namespace ModelElastic;

use Phalcon\DI;
use Phalcon\Mvc\EntityInterface;
use Phalcon\Mvc\User\Component;
use ModelElastic\ModelElasticInterface;

class ModelElastic extends Component implements EntityInterface, ModelElasticInterface{

    protected $_connection = null;

    protected $_index      = null; // database

    protected $_source     = null;

    protected $_primary    = null;

    protected $_errorMessages = null;

    protected $_operationMade = 0;

    const OP_NONE = 0;

    const OP_CREATE = 1;

    const OP_UPDATE = 2;

    const OP_DELETE = 3;

    /**
     * _construct
     */
    public final function  __construct()
    {
        $this->fireEvent("initialize");
    }//end __construct

    /**
     * setConnection
     * @param array $connection 
     */
    public function setConnection($connection =null)
    {
        if($connection){
            $this->_connection = $connection;
        }
    }//setConnection

    /**
     * getConnection
     *  @return [type] [description]
     */
    public function getConnection()
    {
        if(!$this->_connection){
            $this->_connection = $this->elastic; // $this->elastic is di
        }
        return $this->_connection;
    }//getConnection

    /**
     * setIndex: set database index
     */
    public function setIndex($index=null)
    {
        if($index){
            $this->_index = $index;
        }
    }//end

    /**
     *  get database index
     * @return database index
     */
    public function getIndex()
    {
        if(!$this->_index){
            $this->_index = $this->config->elastic->index;
        }
        return $this->_index;
    }//getIndex

    /**
     * setId : overwrite setId
     * @param [type] $id [description]
     * @todo
     */
    public function setId($id)
    {
        if(!$this->id){
            $this->id = $id;
        }
        return $this->id;
    }   //setId

    /**
     * getId: overwrite getId, 
     * for zephir, let method = "get" . camelize(name);
     * @return id
     * @todo
     */
    public function getId()
    {
        return $this->id;
    }//getId

    /**
     * assign: assign data to the model
     * @param  array $data 
     * @return void
     */
    public function assign($data)
    {
    
        $refl = new \ReflectionClass($this);
        if(is_array($data)){
            foreach($data as $key=>$value){
                //* ignore non-property key
                if($refl->hasProperty($key)){
                    $property = $refl->getProperty($key);
                    if ($property instanceof \ReflectionProperty) {
                        $property->setValue($this, $value);
                    }//end if
                }//end if
            }//end foreach
        }//end if
    }//end assign


    /**
     * setSource: customise table
     * @param string $source
     */
    public function setSource($source=null)
    {
        if($source){
            $this->_source = $source;
        }
    }//end setSource

    /**
     * getSource 
     * @return [type] [description]
     */
    public function getSource()
    {
        if(!$this->_source){
            $className = get_called_class();
            //remove namespace
            $className = substr($className, strrpos($className, '\\') + 1);
            //camel to underscore
            $className = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className));
            $this->_source = $className;
        }

        return $this->_source;
    }//end getSource

    /**
     * fireEvent: fire an event
     * @param  string $eventName
     * @return  void
     */
    public function fireEvent($eventName)
    {
        if (method_exists($this, $eventName)){
            $this->$eventName();
        }
    }//end fireEvent

    /**
     * fireEventCancel: fire an event, if event return false, stops
     * @param  string $eventName 
     * @return boolean
     */
    public function fireEventCancel($eventName)
    {
        if (method_exists($this, $eventName)){
            if($this->$eventName() ===FALSE){
                return FALSE;
            }
        }
        return TRUE;
    }//end fireEventCancel

    /**
     * validationHasFailed, Check whether validation process has generated any messages
     * @return boolean
     */
    public function validationHasFailed()
    {
        $errorMessages = $this->_errorMessages;

        if(is_array($errorMessages)){
            return count($errorMessages)>0;
        }

        return FALSE;
    }//end validationHasFailed

    /**
     * [getMessages description]
     * @return [type] [description]
     */
    public function getMessages()
    {
        return $this->_errorMessages;
    }//end getMessages


    /**
     * Appends a customized message on the validation process
     * @param  MessageInterface $message 
     * @return [type]          [description]
     */
    public function appendMessage($message)
    {
        $this->_errorMessages[] = $message;
        return $this;   
    }//end appendMessage

    /**
     * [validate description]
     * @param  [type] $validator [description]
     * @return [type]            [description]
     */
    protected function validate($validator)
    {
        if($validator->validate($this) === FALSE){

            foreach($validator->getMessages() as $message){
                $this->_errorMessages[] = $message; //add message object to array
            }
        }
    }//end validate

    /**
     * save data
     * $user = new users();
     * $saveData = ['username'=>'abc','email'=>'abcd@gmail.com'];
     * $user->assign($saveData);
     * $feedback = $user->save();
     * OR
     * $feedback = $user->save($saveData);
     * if(!$feedback){
     *      echo $user->getMessage();
     * }
     * @param  array $data data object property in array
     * @return boolean 
     */
    public function save($data=null)
    {
    
        if(is_array($data)){
            $this->assign($data);
        }

        $exist = FALSE;

        if(isset($this->id)){
            $exist = $this->_exists();
        }

        $client          = $this->getConnection();
        $params['index'] = $this->getIndex();
        $params['type']  = $this->getSource();
        
        try{
            //preSave fire validate
            if($this->_preSave($exist)===FALSE){
                $errorMsg = 'Data Validation Failed';
                throw new \Exception($errorMsg, 1);
            }

            //filter null, empty, keep 0/false, only on first level.
            $saveData = $this->filterArray();
            unset($saveData['id']); //Don't save _id to _source
            if($exist){
                //$saveData  = array_replace_recursive($exist, $saveData);
                $this->_operationMade == self::OP_CREATE;
                $params['id'] = $exist['id'];
                $params['body']['doc'] = $saveData;
                $params['refresh'] = TRUE; //MUST
                $result = $client->update($params);

            }else{
                $this->_operationMade == self::OP_UPDATE;
                $params['body'] = $saveData;
                $result = $client->index($params);
            }

            if(!$result['_id']){
                throw new \Exception("Data not save, not id", 1);
            }

            //after insert/update, assign the data to model object, mainly for id
            $saveData['id'] = $result['_id'];
            $this->assign($saveData);
            $this->fireEvent("afterSave");

            return TRUE;

        }catch (\Exception $e){
            $message = 'debug: '.$e->getMessage(). " line ".__LINE__." at ".__FILE__;
            error_log($message, 0);
            $this->_cancelOperation();
            $this->appendMessage($e->getMessage());
            return FALSE;
        }//end try/catch
        
    }//end save

    /**
     * findById
     * $id = 'AVW3As8PLIeUbRqiIbQz';
     * $r = Users::findById($id);
     * $email = $r->email;
     * 
     * @todo  redo
     * @param  [type] $id [description]
     * @return [type]     [description]
     */
    public static function findById($id)
    {
        if(!$id){
            return FALSE;
        }

        $className = get_called_class();
        $model = new $className();

        try{
            $client          = $model->getConnection();
            $params['index'] = $model->getIndex();
            $params['type']  = $model->getSource();


            if(!is_array($id)){
                $params['id']    = $id; 
                $resp = $client->get($params);

                if(!isset($resp['found']) or (!$resp['found'] ===TRUE)){
                    return FALSE;   
                }
                $row        = $resp['_source'];
                $row['id']  = $resp['_id'];
                return static::cloneResult($model, $row); // build to object
            
            }else{
                $params['body']['ids']  = $id;
                $resp = $client->mget($params);

                $items = [];
                foreach($resp['docs'] as $r){
                    if(!isset($r['found']) or (!$r['found'] ===TRUE)){
                        $items[$r['_id']] = FALSE;   
                    }else{
                        $row        = $r['_source'];
                        $row['id']  = $r['_id'];
                        $items[$r['_id']] = static::cloneResult($model, $row);
                    }
                }//end foreach
                return $items;
            }//end if

        }catch(\Exception $e){
            //var_dump($e->getMessage());
            //die;
            return FALSE;
        }
    }//end findById


    

    /**
     * findFirst description
     *
     * $query['bool']['must'][]['term']['username'] = strtolower('abcd');
     * $query['bool']['must_not'][]['term']['status'] = 1;
     * $sort = ['create_at'=>'desc', 'username'=>'asc', 'user'];
     * $fields = ['username','email']; //return few fields instead of all
     * 
     * $r = Users::findFirst([
     *              'query'      => $query,
     *              'sort'       => $sort,
     *              'fields'     => $fields,
     *                      ]);
     * 
     * @param  array  $opt [description]
     * @return [type]      [description]
     */
    public static function findFirst($opt=array())
    {

        $className = get_called_class();
        $model = new $className();
        return static::_getResultset($opt, $model, TRUE); // unique TRUE means limit 1
    
    }//end findFirst

    /**
     * find description
     * $r = Users::find(); //find all
     * 
     * $query['bool']['must'][]['term']['username'] = strtolower('abcd');
     * $sort['create_at'] ='desc';
     * $sort = ['create_at'=>'desc', 'username'=>'asc', 'user'];
     * $fields = ['username','email']; //return few fields instead of all
     * $r = Users::find([
     *              'query'      => $query,
     *              'sort'       => $sort,
     *              'fields'     => $fields,    
     *                      ]);
     *
     * @param  array  $opt [description]
     * @return [type]      [description]
     */
    public static function find($opt=array())
    {
    
        $className = get_called_class();
        $model = new $className();

        return static::_getResultset($opt, $model, FALSE);//unique FALSE return more than 1
    
    }//end function

    public static function count($opt=array())
    {
        $className = get_called_class();
        $model = new $className();

        return static::_getCountResultset($opt, $model);//unique FALSE return more than 1
    }//count


    public static function scroll($opt=array())
    {
        $className = get_called_class();
        $model = new $className();

        return static::_getScrollResultset($opt, $model);//unique FALSE return more than 1
    }//scroll

    /**
     * delete description
     * @return boolean
     * @todo  need test
     */
    public function delete()
    {

        try {
            if(!$this->id){
                throw new \Exception("The document cannot be deleted because it doesn't exist", 1);
            }//end if

            if($this->fireEventCancel("beforeDelete") === FALSE){
                return FALSE;
            }

            $client          = $this->getConnection();
            $params['index'] = $this->getIndex();
            $params['type']  = $this->getSource();
            $params['id']    = $this->id;
            $params['refresh'] = TRUE;
            $result = $client->delete($params);

        }catch(\Exception $e){
            $this->appendMessage($e->getMessage());
            return FALSE;
        }

        return TRUE;

    }//end delete


    /**
     * [_preSave description]
     * @param  [type] $exists [description]
     * @return [type]         [description]
     */
    protected function _preSave($exists)
    {
        if($this->fireEventCancel('beforeValidation') === FALSE){
            return FALSE;
        }

        if(!$exists){
            if($this->fireEventCancel('beforeValidationOnCreate') === FALSE){
                return FALSE;
            }//end beforeValidationOnCreate
        }else{
            if($this->fireEventCancel('beforeValidationOnUpdate') === FALSE){
                return FALSE;
            }//end if beforeValidationOnUpdate
        }//end if !exist

        if($this->fireEventCancel('validation')===FALSE){
            return FALSE;
        }

        return TRUE;
    }//end _preSave



    /**
     * [_exists description]
     * @param  [type] $opt [description]
     * @return [type]      [description]
     */
    protected function _exists()
    {
        $idValue = isset($this->id) ? $this->id : '';

        try{
            if(!$idValue){
                throw new \Exception("_id is required for _exists", 1);
            }

            $client          = $this->getConnection();
            $params['index'] = $this->getIndex();
            $params['type']  = $this->getSource();
            $params['id']    = $idValue;

            $resp = $client->get($params);

            if(!isset($resp['found']) or (!$resp['found'] ===TRUE)){
                throw new \Exception("get document not found", 1);
            }

            $result        = $resp['_source'];
            $result['id']  = $resp['_id'];

        }catch (\Exception $e){

            $result = FALSE;
        }
        
        return $result;
    }//end _exists

        /**
     * Cancel the current operation
     */
    protected function _cancelOperation()
    {
        if ($this->_operationMade == self::OP_DELETE) {
            $this->fireEvent("notDeleted");
        } else {
            $this->fireEvent("notSaved");
        }
    }//_cancelOperation


    /**
     * [readAttribute description]
     * @param  [type] $attribute [description]
     * @return [type]            [description]
     */
    public function readAttribute($attribute)
    {

        if(!isset($this->$attribute)){
            return null;
        }
        return $this->$attribute;
    }//end readAttribute

    /**
     * [writeAttribute description]
     * @param  [type] $attribute [description]
     * @param  [type] $value     [description]
     * @return [type]            [description]
     */
    public function writeAttribute($attribute, $value)
    {
        $this->$attribute = $value;
    }//end writeAttribute


    /**
     * Returns a cloned model
     * @param  ModelElasticInterface $model  
     * @param  ElasticSearch _source $document
     * @return ModelElasticInterface
     */
    public static function cloneResult($model, $document)
    {

        $clonedModel = clone $model;
        foreach($document as $key => $value){
            $clonedModel->writeAttribute($key,$value);
        }

        $clonedModel->fireEvent('afterFetch');

        return $clonedModel;
    }//cloneResult
    
    /**
     * _getResultset max return max_total rows, default 10000
     * @param  [type] $opt    [description]
     * @param  [type] $model  [description]
     * @param  [type] $unique [description]
     * @return [type]         [description]
     */
    protected static function _getResultset($opt, $model, $unique)
    {

        if(isset($opt['class'])){
            $className = $opt['class'];
            $base      = new $className();
        }else{
            $base = $model;
        }

        
        $startTime = microtime(true);
    
        $client = $model->getConnection();
        $index  = $model->getIndex();
        $source = $model->getSource();

        if(!$source){
            throw new \Exception("Method getSource() returns empty string", 1);
        }

        //* this is maxTotal is for elastic search shard performance
        //https://www.elastic.co/guide/en/elasticsearch/guide/current/pagination.html
        //check problem of "Deep Paging in Distributed Systems"
        $maxTotal = isset($opt['max_total'])   ? intval($opt['max_total']) : 5000;
        $page     = isset($opt['page'])        ? intval($opt['page'])      : 1;
        $limit    = isset($opt['limit'])       ? intval($opt['limit'])     : 30;

        $query    = isset($opt['query'])       ? $opt['query']             : array();
        $sort     = isset($opt['sort'])        ? $opt['sort']              : array();
        $fields   = isset($opt['fields'])      ? $opt['fields']            : array();
        $groupby  = isset($opt['aggs'])        ? $opt['aggs']              : array();
        $scroll   = isset($opt['scroll'])      ? $opt['scroll']            : "";
        

        if(!$query){
            $query = array("match_all"=>[]);
        }
        
        $offset = ($page -1) * $limit;

        $params['index'] = $index;
        $params['type']  = $source;
        $params['size']  = $limit;
        $params['body']['query']  = $query;

        if($limit > 0){
            $params['from']  = $offset;
        }

        if($sort){
            if(!is_array($sort)){
                $sort[] = $sort; 
            }

            foreach($sort as $k => $s){
                $params['sort'][] = $k.':'.$s; //see elasticsearch php API source code.  
            }//end foreach
        }//end sort

        if($fields){
            if(!is_array($fields)){
                $fields[] = $fields;
            }
            $params['fields'] = $fields;
        }//end fields

        if($groupby){
            $params['body']['aggs'] = $groupby;
        }

        if($scroll){
            $params['scroll'] = $scroll;
        }

        if($unique === TRUE){
            $params['from'] = 0;
            $params['size'] = 1;
        }
       
        $response = $client->search($params);
        
        $total    = isset($response['hits']['total']) ? intval($response['hits']['total']) : 0;
        $hits     = isset($response['hits']['hits']) ? $response['hits']['hits'] : [] ;
        $aggs     = isset($response['aggregations']) ? $response['aggregations'] : [] ;
        $scrollId = isset($response['_scroll_id'])   ? $response['_scroll_id']   : "" ;

        $items = [];
        if($total>0 and $hits){
            foreach($hits as $k=>$v){
                $row       = $v['_source'];
                $row['id'] = $v['_id'];
                $items[$k] = static::cloneResult($base, $row); // build to object
            }//end foreach
        }//end if

        if($unique === TRUE){
            if(!count($items)){
                return $items;
            }
            return $items[0];
        }

        if($maxTotal < $total){
            $total = $maxTotal;
        }

        $totalPage = ($limit ===0 ) ? 0 : ceil($total/$limit);
        $current = ($page > 0)    ? $page       : 1;
        $before  = ($current > 1) ? $current -1 : 1;
        $next    = ($current < $totalPage) ? $current+1 : $totalPage;

        $result = new \stdClass();

        $result->total_items = $total;
        $result->total_pages = $totalPage;
        $result->first       = 1;
        $result->before      = $before;
        $result->current     = $current;
        $result->next        = $next;
        $result->last        = $totalPage;
        $result->items       = $items;

        if($aggs){
            $result->aggs   = $aggs;   
        }

        if($scrollId){
            $result->scroll_id = $scrollId;
        }

        return $result;

    }//end _getResultset

    /**
     * [_getCountResultset description]
     * @param  [type] $opt   [description]
     * @param  [type] $model [description]
     * @return [type]        [description]
     */
    protected static function _getCountResultset($opt, $model)
    {
        if(isset($opt['class'])){
            $className = $opt['class'];
            $base      = new $className();
        }else{
            $base = $model;
        }

        $client = $model->getConnection();
        $index  = $model->getIndex();
        $source = $model->getSource();

        if(!$source){
            throw new \Exception("Method getSource() returns empty string", 1);
        }
        
        $query    = isset($opt['query'])  ? $opt['query'] : array();
        
        if(!$query){
            $query = array("match_all"=>[]);
        }
        
        $params['index'] = $index;
        $params['type']  = $source;
        $params['body']['query']  = $query;
        
        $response = $client->count($params);

        $total    = isset($response['count']) ? intval($response['count']) : 0;
       
        return $total;
    }//_getCountResultset


    /**
     * _getScrollResultset max return max_total rows, default 10000
     * @param  [type] $opt    [description]
     * @param  [type] $model  [description]
     * @param  [type] $unique [description]
     * @return [type]         [description]
     */
    protected static function _getScrollResultset($opt, $model){

        if(isset($opt['class'])){
            $className = $opt['class'];
            $base      = new $className();
        }else{
            $base = $model;
        }

        $client = $model->getConnection();
        //$index  = $model->getIndex();
        //$source = $model->getSource();

        /***
        if(!$source){
            throw new \Exception("Method getSource() returns empty string", 1);
        }
        ****/

        $scroll   = isset($opt['scroll'])      ? $opt['scroll']            : "1m"; // 1 mintue
        $scrollId = isset($opt['scroll_id'])   ? $opt['scroll_id']         : 0;
        
        if(!$scrollId){
            throw new \Exception("scroll_id is required", 1);
        }
        
        $params['scroll']    = $scroll;
        $params['scroll_id'] = $scrollId;
       
    
        $response = $client->scroll($params);
        
        $total    = isset($response['hits']['total']) ? intval($response['hits']['total']) : 0;
        $hits     = isset($response['hits']['hits']) ? $response['hits']['hits'] : [] ;
        $scrollId = isset($response['_scroll_id'])   ? $response['_scroll_id']   : "" ;

        $items = [];
        if($total>0 and $hits){
            foreach($hits as $k=>$v){
                $row       = $v['_source'];
                $row['id'] = $v['_id'];
                $items[$k] = static::cloneResult($base, $row); // build to object
            }//end foreach
        }//end if

       
        $result = new \stdClass();
        $result->items       = $items;
        if($scrollId){
            $result->scroll_id = $scrollId;
        }
        
        return $result;

    }//end _getResultset

    /**
     * filterArray
     * @return class properties in array
     */
    public function filterArray()
    {
        $refl = new \ReflectionClass($this);

        $data = [];
        foreach ($refl->getProperties() as $property){
            $name = $property->name;
            if ($property->class == $refl->name){
                $data[$property->name] = $this->$name;
            }
        }

        //* filter null, empty data/ keep 0 and FALSE
        // was using Format::array_filter_recursive 2016/07/21, now only filter first level
        $data = array_filter($data, function($var){
                            if(is_bool($var) or is_numeric($var) or is_array($var)){
                                return TRUE;
                            }
                            $var = preg_replace('/\s+/','',$var);
                            return ($var !==NULL && $var !=='');
                            }); //return to false to unset

        return $data;
    }//end filterArray

}//end class