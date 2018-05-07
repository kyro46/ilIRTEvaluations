<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Displays the basic matrix for IRT calculations which is transmitted to OpenCPU
 */
class ilExteEvalOpenCPUBaseData extends ilExteEvalTest
{
	/**
	 * @var bool	evaluation provides a single value for the overview level
	 */
	protected $provides_value = false;

	/**
	 * @var bool	evaluation provides data for a details screen
	 */
	protected $provides_details = true;

	/**
	 * @var array list of allowed test types, e.g. array(self::TEST_TYPE_FIXED)
	 */
	protected $allowed_test_types = array();

	/**
	 * @var array	list of question types, e.g. array('assSingleChoice', 'assMultipleChoice', ...)
	 */
	protected $allowed_question_types = array();

	/**
	 * @var string	specific prefix of language variables (lowercase classname is default)
	 */
	protected $lang_prefix = 'tst_OpenCPUBaseData';

	/**
	 * Calculate and get the single value for a test
	 *
	 * @return ilExteStatValue
	 */
	public function calculateValue()
	{
        return new ilExteStatValue;
	}

	/**
	 * Calculate the basic matrix for IRT calculations
	 *
	 * @return ilExteStatDetails
	 */
	public function calculateDetails()
	{
        $details = new ilExteStatDetails();
        $details->columns = array (
        	ilExteStatColumn::_create('active_id',' ',ilExteStatColumn::SORT_NUMBER),
        );

        foreach ($this->data->getAllQuestions() as $question)
        {
        	array_push($details->columns, ilExteStatColumn::_create($question->question_id,$question->question_id,ilExteStatColumn::SORT_NUMBER));
        }
        $active_id_array = array();
        
        foreach ($this->data->getAllQuestions() as $question)
        {
        	foreach ($this->data->getAnswersForQuestion($question->question_id) as $answer) 
        	{
        		$active_id_array[$answer->active_id][$question->question_id] = $answer->reached_points;
        	}
        }

        foreach($active_id_array as $active_id => $reached_points_array) {
        	$details->rows[] = array(
        			'active_id' => ilExteStatValue::_create($active_id, ilExteStatValue::TYPE_NUMBER, 0),
        	);
        	foreach($reached_points_array as $question_id => $reached_points){
        		$details->rows[count($details->rows)-1][$question_id] = ilExteStatValue::_create($reached_points, ilExteStatValue::TYPE_NUMBER, 2);
        	}
        }
        return $details;
	}
}