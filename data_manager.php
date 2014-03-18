<?php

/*  Dependency: Simple_Template, (Simple_Validation)  */

class Data_Manager {

	const MODE_DISPLAY = 0;
	const MODE_INPUT = 1;
	const MODE_CONFIRM = 2;
	const MODE_COMPLETE = 3;
	const MODE_NAME = '_mode';
	private $_mode = -1;
	private $_submit_method = '';
	private $_error_message_delimiter = '<br>';
	private $_submit_names = array();
	private $_input_data = array();
	private $_current_values = array();
	private $_template_pathes = array();
	private $_form_properties = array();
	private $_custom_validation_rules = array();
	private $_error_message_properties = array(
			'id' => 'error_message', 
			'class' => 'error_message'
	);
	private $_return_button_properties = array(
			'type' => 'submit', 
			'id' => 'return_button', 
			'name' => 'return_button', 
			'class' => 'return_button',
			'value' => 'Return'
	);
	private $_file_params = array();
	private $_radio_delimiters = array();
	private $_st, $_sv;
	
	public function __construct() {
		
		$this->_st = new Simple_Template();
		
	}
	
	public function setInputData($input_data) {
		
		$this->_input_data = $input_data;
		
		foreach ($this->_input_data as $input_name => $values) {
			
			if($values['type'] == 'submit') {
				
				$this->_submit_names[] = $input_name;
				$this->getSubmitValue($input_name);
				
			}
			
		}
		
	}
	
	public function setCurrentValues($values) {
		
		$this->_current_values = $values;
		
	}
	
	public function setErrorMessages($error_messages) {
		
		foreach ($error_messages as $input_name => $messages) {
			
			$display_messages = array();
			
			foreach ($messages as $rule_name => $message) {
					
				$display_messages[] = $message;
					
			}

			$display_message = implode($this->_error_message_delimiter, $display_messages);
			$set_value = $this->getTag('div', $this->_error_message_properties). $display_message .'</div>';
			$this->_st->set($input_name .'_error', $set_value);
			
		}
		
	}
	
	public function setTemplatePathes($pathes) {
		
		$this->_template_pathes = $pathes;
		
	}
	
	public function setMode($mode) {
		
		$this->_mode = $mode;
		
	}
	
	public function getMode() {
		
		return $this->_mode;
		
	}

	public function getSubmitMode() {
	
		return $this->getSubmitValue(self::MODE_NAME);
	
	}
	
	public function setReturnButtonProperties($properties) {

		$properties['type'] = 'submit';
		$this->_return_button_properties = $properties;
		
	}
	
	public function setErrorMessageProperties($properties, $delimiter='') {
		
		$this->_error_message_properties = $properties;
		$this->_error_message_delimiter = $delimiter;
		
	}
	
	public function setRadioDelimiters($delimiters) {
		
		$this->_radio_delimiters = $delimiters;
		
	}
	
	public function setFormProperties($properties) {
		
		$this->_submit_method = $properties['method'];
		$this->_form_properties = $properties;
		
	}
	
	public function setFileParams($params) {
		
		$this->_file_params = $params;
		
	}
	
	public function setCustomValidationRule($rule_name, $anonymous_function) {
		
		$this->_custom_validation_rules[$rule_name] = $anonymous_function;
		
	}
	
	public function validate($rules) {

		$this->_sv = new Simple_Validation();
		$this->_sv->setCheckValues($this->_current_values);
		$this->_sv->setRules($rules);
		
		foreach ($this->_custom_validation_rules as $rule_name => $anonymous_function) {
			
			$this->_sv->setCustomRule($rule_name, $anonymous_function);
			
		}
		
		$validate_result = $this->_sv->validate();
		
		if(!$validate_result || $this->isReturn()) {
		
			$this->setErrorMessages($this->_sv->getErrorMessages());
			$this->_mode = Data_Manager::MODE_INPUT;
		
		} else if($this->getSubmitMode() == Data_Manager::MODE_INPUT) {
		
			$this->_mode = Data_Manager::MODE_CONFIRM;
		
		} else if($this->getSubmitMode() == Data_Manager::MODE_CONFIRM) {
		
			$this->_mode = Data_Manager::MODE_COMPLETE;
		
		} else {
			
			$this->_mode = Data_Manager::MODE_DISPLAY;
			
		}
		
		return $validate_result;
		
	}
	
	public function getContents() {

		$this->_st->read($this->_template_pathes[$this->_mode]);
		
		foreach ($this->_input_data as $input_name => $input_params) {

			$input_type = $input_params['type'];
			$input_value = $input_params['value'];
			
			if($this->_mode == self::MODE_DISPLAY || $this->_mode == self::MODE_CONFIRM) {
				
				$hidden_value = '';
				
				if($input_type == 'submit') {
					
					if($this->_mode == self::MODE_CONFIRM) {
						
						$this->_st->set($input_name, $this->getInputTag($input_name, $input_params));
						
					}
					
					continue;
					
				} else if($input_type == 'file') {

					$file_properties = $this->_file_params['properties'][$input_name];
					
					if(isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] == UPLOAD_ERR_OK) {
						
						$file_name = $_FILES[$input_name]['name'];
						$file_extension = $this->getExtension($file_name);
						$file_type = $_FILES[$input_name]['type'];
						$file_tmp_name = $_FILES[$input_name]['tmp_name'];
						$save_filename = md5($input_value . time()) . $file_extension;
						$destination = $this->_file_params['dir']['system'] . $save_filename;
						
						if(@move_uploaded_file($file_tmp_name, $destination)) {
							
							if(substr($file_type, 0, 5) == 'image' && $this->isImageExtension($file_extension)) {
								
								$file_properties['src'] = $this->_file_params['dir']['url'] . $save_filename;
								$input_value = $this->getTag('img', $file_properties);
								
							} else {
								
								$input_value = $this->getTag('div', $file_properties) . $file_name .'</div>';
								
							}
							
							$hidden_value = $save_filename;
							
						}
						
					} else if(!empty($this->getCurrentValue($input_name))) {
						
						$filename = $this->getCurrentValue($input_name);
						$file_extension = $this->getExtension($filename);
						
						if($this->isImageExtension($file_extension)) {
							
							$file_properties['src'] = $this->_file_params['dir']['url']  . $filename;
							$input_value = $this->getTag('img', $file_properties);
							
						}
						
					}
					
				} else if(is_array($input_value)) {

					$hidden_value = $this->getCurrentValue($input_name);
					$input_value = $input_value[$hidden_value];
					
				} else {
					
					$input_value = $hidden_value = $this->getCurrentValue($input_name);
					
				}
				
				$hidden_tag = $this->getTag('input', array(
						'type' => 'hidden', 
						'name' => $input_name, 
						'value' => $hidden_value
				));
				$this->_st->set($input_name, $input_value . $hidden_tag);
				
			} else {
				
				if($input_type == 'file' 
						&& $this->getMode() == self::MODE_INPUT 
						&& $this->isReturn()) {
					
					$destination = $this->_file_params['dir']['system'] . $this->getCurrentValue($input_name);
					
					if(!empty($this->getCurrentValue($input_name)) && file_exists($destination)) {
						
						unlink($destination);
						
					}
					
				}
				
				$this->_st->set($input_name, $this->getInputTag($input_name, $input_params));
				
			}
			
		}
		
		if($this->_mode == self::MODE_CONFIRM) {
			
			$this->_st->set('return_button', $this->getTag('input', $this->_return_button_properties));
			
		}
		
		$mode_hidden_tag = '';
		
		if($this->_mode != self::MODE_DISPLAY) {
			
			$mode_hidden_tag = $this->getTag('input', array(
					'type' => 'hidden', 
					'name' => self::MODE_NAME, 
					'value' => $this->_mode
			));
			
		}
		
		return $this->_st->get() . $mode_hidden_tag;
		
	}
	
	public function form($form_mode) {
		
		if($this->_mode == self::MODE_DISPLAY || $this->_mode == self::MODE_COMPLETE) {
			
			return '';
			
		} else if($form_mode == 'end') {
			
			return '</form>';
			
		}
		
		return $this->getTag('form', $this->_form_properties);
		
	}
	
	public function isSubmit() {
		
		foreach ($this->_submit_names as $submit_name) {
			
			if(!empty($this->getSubmitValue($submit_name))) {
					
				return true;
					
			}
			
		}
		
		return false;
		
	}
	
	public function isReturn() {
		
		$return_name = $this->_return_button_properties['name'];
		return (!empty($this->getSubmitValue($return_name)));
		
	}
	
	public function isSend() {
		
		return ($this->isSubmit() || $this->isReturn());
		
	}
	
	public function isComplete() {
		
		return ($this->getMode() == self::MODE_COMPLETE);
		
	}
	
	private function getSubmitValue($name) {
		
		if(isset($_POST[$name])) {
		
			return $_POST[$name];
		
		} else if(isset($_GET[$name])) {
		
			return $_GET[$name];
		
		}
		
		return '';
		
	}
	
	private function isPost() {
		
		return (strtolower($this->_submit_method) == 'post');
		
	}
	
	private function getInputTag($input_name, $params) {

		$value = $params['value'];
		$type = $params['type'];
		
		if($type == 'text' 
				|| $type == 'hidden'
				|| $type == 'checkbox'
				|| $type == 'submit'
				|| $type == 'file') {

			$properties = $params['properties'];
			$properties['id'] = $input_name;
			$properties['name'] = $input_name;
			$properties['type'] = $type;
			
			if($type == 'checkbox') {

				$array_keys = array_keys($value);
				$properties['value'] = $array_keys[0];
				$label = '<label for="'. $input_name .'">'. $value[$array_keys[0]] .'</label>';
				
				if($this->getCurrentValue($input_name) == $properties['value']) {
					
					$properties['checked'] = 'checked';
						
				}
				
				return $this->getTag('input', $properties) . $label;
				
			} else {
				
				if($type != 'file' && $type != 'submit') {

					$properties['value'] = $this->getCurrentValue($input_name);
					
				} else if($type == 'submit') {

					$properties['value'] = $value;
					
				}
				
				return $this->getTag('input', $properties);
				
			}
			
		} else if($type == 'radio') {
			
			$index = 0;
			$radio_tags = array();
			
			foreach ($value as $radio_value => $radio_label) {
				
				$radio_id = $input_name .'_'. $index;
				$label = '<label for="'. $radio_id .'">'. $radio_label .'</label>';
				$radio_properties = array_merge($params['properties'][$index], array(
						'name' => $input_name, 
						'type' => 'radio', 
						'value' => $radio_value, 
						'id' => $radio_id
				));
				
				if($this->getCurrentValue($input_name) == $radio_value
						|| ($index == 0 && empty($this->getCurrentValue($input_name)))) {
					
					$radio_properties['checked'] = 'checked';
					
				}
				
				$radio_tags[] = $this->getTag('input', $radio_properties) . $label;
				$index++;
				
			}
			
			return implode($this->_radio_delimiters[$input_name], $radio_tags);
			
		} else if($type == 'select') {

			$properties['id'] = $input_name;
			$properties['name'] = $input_name;
			$select_value = $properties['value'];
			unset($properties['value']);
			$option_tags = array();
			
			foreach ($value as $select_value => $select_label) {
				
				$option_propertyies = array(
						'value' => $select_value
				);
				
				if($this->getCurrentValue($input_name) == $select_value) {
						
					$option_propertyies['selected'] = 'selected';
						
				}
				
				$option_tags[] = $this->getTag('option', $option_propertyies). $select_label .'</option>';
				
			}
			
			return $this->getTag('select', $properties) . implode('', $option_tags) .'</select>';
			
		} else if($type == 'textarea') {
			
			$properties['id'] = $input_name;
			$properties['name'] = $input_name;
			return $this->getTag('textarea', $properties). $this->getCurrentValue($input_name) .'</textarea>';
			
		}
		
	}
	
	private function getPropertyies($properties) {
		
		$form_property = '';
		
		foreach ($properties as $key => $value) {
				
			$form_property .= ' '. $key .'="'. $value .'"';
				
		}
		
		return $form_property;
		
	}
	
	private function getTag($tag_name, $properties) {
		
		if(!is_array($properties)) {
			
			$properties = array();
			
		}
		
		return '<'. $tag_name . $this->getPropertyies($properties) .'>';
		
	}
	
	private function getExtension($filename) {
		
		return substr($filename, strrpos($filename, '.'));
		
	}
	
	private function isImageExtension($file_extension) {
		
		$image_extensions = array(
				'.png', '.jpg', '.jpeg', '.gif', '.bmp', '.webp', '.svg'
		);
		
		return (in_array($file_extension, $image_extensions));
		
	}
	
	private function getCurrentValue($value_key) {
	
		if(isset($this->_current_values[$value_key])) {
				
			return $this->_current_values[$value_key];
				
		} else if(preg_match('|([^\[]+)\[([0-9]+)\]$|', $value_key, $matches)) {
				
			$value_key = $matches[1];
			$index = $matches[2];
				
			if(isset($this->_current_values[$value_key][$index])) {

				return $this->_current_values[$value_key][$index];
	
			}
				
		}
	
		return '';
	
	}
	
}
