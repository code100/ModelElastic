<?php

namespace ModelElastic;

interface ModelElasticInterface
{	
	/**
	 * Sets a value for the _id propery
	 * @param mixed id
	 */
	public function setId($id);

	/**
	 * Returns the value of the _id property
	 * @return elastic document _id
	 */
	public function getId();

	/**
	 * Returns elastic type name mapped in the model
	 * @return string
	 */
	public function getSource();

	/**
	 * return a cloned elastic model
	 * @param  [type] $model    [description]
	 * @param  [type] $document [description]
	 * @return ModelElasticInterface
	 */
	public static function cloneResult($model, $document);

	/**
	 * Fires an event
	 * @param  string $eventName 
	 * @return boolean
	 */
	public function fireEvent($eventName);

	/**
	 * Fires an event, stops if one of the callbacks/listeners returns boolean false
	 * @param  string $eventName 
	 * @return boolean
	 */
	public function fireEventCancel($eventName);

	/**
	 * validationHasFailed
	 * @return [type] [description]
	 */
	public function validationHasFailed();

	/**
	 * getMessages
	 * @return [type] [description]
	 */
	public function getMessages();

	/**
	 * appendMessage 
	 * @return [type] [description]
	 */
	public function appendMessage($message);

	/**
	 * [save description]
	 * @return [type] [description]
	 */
	public function save();

	/**
	 * [findById description]
	 * @param  [type] $id [description]
	 * @return [type]     [description]
	 */
	public static function findById($id);

	/**
	 * [findFirst description]
	 * @param  [type] $parameters [description]
	 * @return [type]             [description]
	 */
	public static function findFirst($parameters);

	/**
	 * [find description]
	 * @param  [type] $parameters [description]
	 * @return [type]             [description]
	 */
	public static function find($parameters);


	/**
	 * [delete description]
	 * @return [type] [description]
	 */
	public function delete();

}