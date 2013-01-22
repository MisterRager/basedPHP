<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'MappedObject.class.php');

class TVShow extends MappedObject {

	protected $fields = array(
		'id' => 'int',
		'title' => 'string',
		'description' => 'string',
		'image_url' => 'string',
		'trailer_url' => 'string',
		'points_worth' => 'int'
	);

	protected $required_fields = array(
		'title', 'points_worth'
	);

	public function __beforeSave() {
		//set default value for points
		if($this->points_worth === NULL) {
			$this->points_worth = 0;
		}
		parent::__beforeSave();
	}

	public function makeAiring(TVStation $station, DateTime $start_time, DateTime $end_time) {
		$air = new TVAirDate();
		$air->start_time = $start_time->getTimestamp();
		$air->end_time = $end_time->getTimestamp();
		$air->show_id = $this->id;
		$air->station_id = $station->id;

		return $air;
	}
}
