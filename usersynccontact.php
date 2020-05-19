<?php

// no direct access

defined('_JEXEC') or die;

class PlgUserUserSyncContact extends JPlugin
{
	public function onUserAfterSave($user, $isnew, $success, $msg)
	{
//have some functions done.
		//find contact_id in #__contact_details
		function GetContactDetails($user)
		{
			// Get a db connection.
			$db = JFactory::getDbo();
			// Create a new query object.
			$query = $db->getQuery(true);
			$query->select($db->quoteName(array('user_id', 'id', 'fax', 'mobile', 'misc' ))); //only need contact ID but hey, for future reference....
			$query->from($db->quoteName('#__contact_details'));
			$query->where($db->quoteName('user_id') . ' LIKE ' . $db->quote($user['id']));
			$db->setQuery($query);
			// Load the results as a list.
			$results = $db->loadObjectList();
			//check if populated.
			if(!$results) {
				//no contact, send message
				JFactory::getApplication()->enqueueMessage('Make sure to <strong>resave</strong> new user <strong>' . $user['name'] . '</strong> in order to sync with contacts<br>or contact the admin to change the plugin sort order', 'warning');
			}
			return $results;
		}
		//check if an entry (field_id) in #__fields_values exists for contact/user(item_id)
		function GetFieldExists($field_id, $item_id)
		{
			// Get a db connection.
			$db = JFactory::getDbo();
			// Create a new query object.
			$query = $db->getQuery(true);
			$query->select($db->quoteName(array('field_id', 'item_id', 'value')));
			$query->from($db->quoteName('#__fields_values'));
			$query->where($db->quoteName('field_id') . ' LIKE ' . $db->quote($field_id));
			$query->where($db->quoteName('item_id') . ' LIKE ' . $db->quote($item_id));
						
			$db->setQuery($query);
			// Load the results as a list.
			$results = $db->loadObjectList();
			//check if populated
			if(!$results) {
				//something to do someday maybe ;)
			}
			return $results;
		}		
		//store values in DataBase
		function StoreValues($item_id, $field_id, $value)
		{
			//Get a db connection.
			$db = JFactory::getDbo();
			//Create a new query object.
			$query = $db->getQuery(true);
			//fields to update
			$fields = array(
				$db->quoteName('field_id') . ' = ' . $db->quote($field_id),
				$db->quoteName('item_id') . ' = ' . $db->quote($item_id->id),
				$db->quoteName('value') . ' = ' . $db->quote($value)
			);
			//conditions
			$conditions = array(
				$db->quoteName('field_id') . ' = ' . $db->quote($field_id),
				$db->quoteName('item_id') . ' = ' . $db->quote($item_id->id),
			);
			//check for existing fields
			$fields_values = GetFieldExists($field_id, $item_id->id);
			//no fields? insert.. else update
			if(!$fields_values)
			{
				$query->insert($db->quoteName('#__fields_values'))->set($fields);
			}
			else
			{
				$query->update($db->quoteName('#__fields_values'))->set($fields)->where($conditions);
			}

			$db->setQuery($query);
			$result = $db->execute();
		}
		
		
//start some workflow
		
		// If the user wasn't stored we don't resync
		if (!$success)
		{
			return false;
		}
		
		// If the user isn't new we can do things here. But we don't for now.
		if (!$isnew)
		{
			//return false; 
		}
		
//save user profile data in contact details data
	
		// Get a db connection.
		$db = JFactory::getDbo();
		// Create a new query object.
		$query = $db->getQuery(true);
		//address1/2 correction
		if($user['profile']['address2'])
		{
			$address = $user['profile']['address1'] . PHP_EOL . $user['profile']['address2'];
		}
		else
		{
			$address = $user['profile']['address1'];
		}
		//default fields to update
		$fields = array(
			$db->quoteName('address') . ' = ' . $db->quote($address),
			$db->quoteName('suburb') . ' = ' . $db->quote($user['profile']['city']),
			$db->quoteName('state') . ' = ' . $db->quote($user['profile']['region']),
			$db->quoteName('country') . ' = ' . $db->quote($user['profile']['country']),
			$db->quoteName('postcode') . ' = ' . $db->quote($user['profile']['postal_code']),
			$db->quoteName('telephone') . ' = ' . $db->quote($user['profile']['phone']),
			$db->quoteName('webpage') . ' = ' . $db->quote($user['profile']['website']),
		);
		
//extra: save user custom fields in contact data
		if($this->params->get('mob_id'))
		{
			$mob = GetFieldExists($this->params->get('mob_id'),$user['id']);
			array_push($fields, $db->quoteName('mobile') . ' = ' . $db->quote($mob[0]->value));
		}
		if($this->params->get('fax_id'))
		{
			$fax = GetFieldExists($this->params->get('fax_id'),$user['id']);
			array_push($fields, $db->quoteName('fax') . ' = ' . $db->quote($fax[0]->value));
		}
		if($this->params->get('misc_id'))
		{
			$misc = GetFieldExists($this->params->get('misc_id'),$user['id']);
			array_push($fields, $db->quoteName('misc') . ' = ' . $db->quote($misc[0]->value));
		}
//end of extra		
		
		$conditions = array(
			$db->quoteName('user_id') . ' = ' . $db->quote($user['id']),
		);
		//save to database
		$query->update($db->quoteName('#__contact_details'))->set($fields)->where($conditions);
		$db->setQuery($query);
		$result = $db->execute();
		

//save user data in contact custom fields
		$ids = GetContactDetails($user);

		foreach($ids as $item_id)
		{
			if($this->params->get('favoritebook_id'))
			{
				StoreValues($item_id, $this->params->get('favoritebook_id'), $user['profile']['favoritebook'], '#__fields_values');
			}
			if($this->params->get('aboutme_id'))
			{
				StoreValues($item_id, $this->params->get('aboutme_id'), $user['profile']['aboutme'], '#__fields_values');
			}
			if($this->params->get('dob_id'))
			{
				StoreValues($item_id, $this->params->get('dob_id'), $user['profile']['dob'], '#__fields_values');
			}
			
		

//extra: save user custom fields in contact custom fields
			if($this->params->get('custom_mapping'))
			{
				$mapping = explode(PHP_EOL, $this->params->get('custom_mapping'));
				
				$i=0;
				foreach($mapping as $map)
				{
					$map = explode(':',$map);
					
					//remove any left trace of linefeed or eol as different systems can have different ways of dropping the eol / lf
					$map0 = preg_replace( "/\r|\n/", "", $map[0] );
					$map1 = preg_replace( "/\r|\n/", "", $map[1] );

					$fields = GetFieldExists($map0, $user['id']);
					
					StoreValues($item_id, $map1, $fields[0]->value);

					$i++;
				}
				
			}
//end of extra
		
		}	

		return false;
	}

}
