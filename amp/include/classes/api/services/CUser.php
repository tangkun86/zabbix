<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class containing methods for operations with users.
 *
 * @package API
 */
class CUser extends CApiService {

	protected $tableName = 'users';
	protected $tableAlias = 'u';
	protected $sortColumns = ['userid', 'alias'];

	/**
	 * Get users data.
	 *
	 * @param array  $options
	 * @param array  $options['usrgrpids']		filter by UserGroup IDs
	 * @param array  $options['userids']		filter by User IDs
	 * @param bool   $options['type']			filter by User type [USER_TYPE_ZABBIX_USER: 1, USER_TYPE_ZABBIX_ADMIN: 2, USER_TYPE_SUPER_ADMIN: 3]
	 * @param bool   $options['selectUsrgrps']	extend with UserGroups data for each User
	 * @param bool   $options['getAccess']		extend with access data for each User
	 * @param bool   $options['count']			output only count of objects in result. (result returned in property 'rowscount')
	 * @param string $options['pattern']		filter by Host name containing only give pattern
	 * @param int    $options['limit']			output will be limited to given number
	 * @param string $options['sortfield']		output will be sorted by given property ['userid', 'alias']
	 * @param string $options['sortorder']		output will be sorted in given order ['ASC', 'DESC']
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['users' => 'u.userid'],
			'from'		=> ['users' => 'users u'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'usrgrpids'					=> null,
			'userids'					=> null,
			'mediaids'					=> null,
			'mediatypeids'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'editable'					=> null,
			'selectUsrgrps'				=> null,
			'selectMedias'				=> null,
			'selectMediatypes'			=> null,
			'getAccess'					=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// permission check
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			if (!$options['editable']) {
				$sqlParts['from']['users_groups'] = 'users_groups ug';
				$sqlParts['where']['uug'] = 'u.userid=ug.userid';
				$sqlParts['where'][] = 'ug.usrgrpid IN ('.
					' SELECT uug.usrgrpid'.
					' FROM users_groups uug'.
					' WHERE uug.userid='.self::$userData['userid'].
				')';
			}
			else {
				$sqlParts['where'][] = 'u.userid='.self::$userData['userid'];
			}
		}

		// userids
		if ($options['userids'] !== null) {
			zbx_value2array($options['userids']);

			$sqlParts['where'][] = dbConditionInt('u.userid', $options['userids']);
		}

		// usrgrpids
		if ($options['usrgrpids'] !== null) {
			zbx_value2array($options['usrgrpids']);

			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where'][] = dbConditionInt('ug.usrgrpid', $options['usrgrpids']);
			$sqlParts['where']['uug'] = 'u.userid=ug.userid';
		}

		// mediaids
		if ($options['mediaids'] !== null) {
			zbx_value2array($options['mediaids']);

			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = dbConditionInt('m.mediaid', $options['mediaids']);
			$sqlParts['where']['mu'] = 'm.userid=u.userid';
		}

		// mediatypeids
		if ($options['mediatypeids'] !== null) {
			zbx_value2array($options['mediatypeids']);

			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = dbConditionInt('m.mediatypeid', $options['mediatypeids']);
			$sqlParts['where']['mu'] = 'm.userid=u.userid';
		}

		// filter
		if (is_array($options['filter'])) {
			if (isset($options['filter']['passwd'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('It is not possible to filter by user password.'));
			}

			$this->dbFilter('users u', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			if (isset($options['search']['passwd'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('It is not possible to search by user password.'));
			}

			zbx_db_search('users u', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$userIds = [];

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);

		while ($user = DBfetch($res)) {
			unset($user['passwd']);

			if ($options['countOutput'] !== null) {
				$result = $user['rowscount'];
			}
			else {
				$userIds[$user['userid']] = $user['userid'];

				$result[$user['userid']] = $user;
			}
		}

		if ($options['countOutput'] !== null) {
			return $result;
		}

		/*
		 * Adding objects
		 */
		if ($options['getAccess'] !== null) {
			foreach ($result as $userid => $user) {
				$result[$userid] += ['gui_access' => 0, 'debug_mode' => 0, 'users_status' => 0];
			}

			$access = DBselect(
				'SELECT ug.userid,MAX(g.gui_access) AS gui_access,'.
					' MAX(g.debug_mode) AS debug_mode,MAX(g.users_status) AS users_status'.
					' FROM usrgrp g,users_groups ug'.
					' WHERE '.dbConditionInt('ug.userid', $userIds).
						' AND g.usrgrpid=ug.usrgrpid'.
					' GROUP BY ug.userid'
			);

			while ($userAccess = DBfetch($access)) {
				$result[$userAccess['userid']] = zbx_array_merge($result[$userAccess['userid']], $userAccess);
			}
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys
		if ($options['preservekeys'] === null) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	protected function checkInput(&$users, $method) {
		$create = ($method === 'create');
		$update = ($method === 'update');

		if ($update) {
			$userDBfields = ['userid' => null];

			$dbUsers = $this->get([
				'output' => ['userid', 'alias', 'autologin', 'autologout'],
				'userids' => zbx_objectValues($users, 'userid'),
				'editable' => true,
				'preservekeys' => true
			]);
		}
		else {
			$userDBfields = ['alias' => null, 'passwd' => null, 'usrgrps' => null, 'user_medias' => []];
		}

		$themes = array_keys(Z::getThemes());
		$themes[] = THEME_DEFAULT;
		$themeValidator = new CLimitedSetValidator([
				'values' => $themes
			]
		);
		$alias = [];

		foreach ($users as &$user) {
			if (!check_db_fields($userDBfields, $user)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for user "%s".', $user['alias']));
			}

			// permissions
			if ($create) {
				if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('You do not have permissions to create users.'));
				}

				$dbUser = $user;
			}
			elseif ($update) {
				if (!isset($dbUsers[$user['userid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('You do not have permissions to update user or user does not exist.'));
				}

				if (bccomp(self::$userData['userid'], $user['userid']) != 0 && self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('You do not have permissions to update other users.'));
				}

				$dbUser = $dbUsers[$user['userid']];
			}

			// check if user alias
			if (isset($user['alias'])) {
				// check if we change guest user
				if ($dbUser['alias'] === ZBX_GUEST_USER && $user['alias'] !== ZBX_GUEST_USER) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot rename guest user.'));
				}

				if (!isset($alias[$user['alias']])) {
					$alias[$user['alias']] = $update ? $user['userid'] : 1;
				}
				else {
					if ($create || bccomp($user['userid'], $alias[$user['alias']]) != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Duplicate user alias "%s".', $user['alias']));
					}
				}
			}

			if (isset($user['usrgrps'])) {
				if (empty($user['usrgrps'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('User "%s" cannot be without user group.', $dbUser['alias']));
				}

				// checking if user tries to disable himself (not allowed). No need to check this on creating a user.
				if (!$create && bccomp(self::$userData['userid'], $user['userid']) == 0) {
					$usrgrps = API::UserGroup()->get([
						'usrgrpids' => zbx_objectValues($user['usrgrps'], 'usrgrpid'),
						'output' => API_OUTPUT_EXTEND,
						'preservekeys' => true,
						'nopermissions' => true
					]);
					foreach ($usrgrps as $groupid => $group) {
						if ($group['gui_access'] == GROUP_GUI_ACCESS_DISABLED) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('User may not modify GUI access for himself by becoming a member of user group "%s".', $group['name']));
						}

						if ($group['users_status'] == GROUP_STATUS_DISABLED) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('User may not modify system status for himself by becoming a member of user group "%s".', $group['name']));
						}
					}
				}
			}

			if (isset($user['theme'])) {
				$themeValidator->messageInvalid = _s('Incorrect theme for user "%1$s".', $dbUser['alias']);
				$this->checkValidator($user['theme'], $themeValidator);
			}

			if (isset($user['type']) && (USER_TYPE_SUPER_ADMIN != self::$userData['type'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('You are not allowed to alter privileges for user "%s".', $dbUser['alias']));
			}

			if (isset($user['autologin']) && $user['autologin'] == 1 && $dbUser['autologout'] != 0) {
				$user['autologout'] = 0;
			}

			if (isset($user['autologout']) && $user['autologout'] > 0 && $dbUser['autologin'] != 0) {
				$user['autologin'] = 0;
			}

			if (array_key_exists('passwd', $user)) {
				if (is_null($user['passwd'])) {
					unset($user['passwd']);
				}
				else {
					if ($dbUser['alias'] == ZBX_GUEST_USER && !zbx_empty($user['passwd'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Not allowed to set password for user "guest".'));
					}

					$user['passwd'] = md5($user['passwd']);
				}
			}

			if (isset($user['alias'])) {
				$userExist = $this->get([
					'output' => ['userid'],
					'filter' => ['alias' => $user['alias']],
					'nopermissions' => true
				]);
				if ($exUser = reset($userExist)) {
					if ($create || (bccomp($exUser['userid'], $user['userid']) != 0)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('User with alias "%s" already exists.', $user['alias']));
					}
				}
			}
		}
		unset($user);
	}

	/**
	 * Create user.
	 *
	 * @param array  $users
	 * @param string $users['name']
	 * @param string $users['surname']
	 * @param array  $users['alias']
	 * @param string $users['passwd']
	 * @param string $users['url']
	 * @param int    $users['autologin']
	 * @param int    $users['autologout']
	 * @param string $users['lang']
	 * @param string $users['theme']
	 * @param int    $users['refresh']
	 * @param int    $users['rows_per_page']
	 * @param int    $users['type']
	 * @param array  $users['user_medias']
	 * @param string $users['user_medias']['mediatypeid']
	 * @param string $users['user_medias']['address']
	 * @param int    $users['user_medias']['severity']
	 * @param int    $users['user_medias']['active']
	 * @param string $users['user_medias']['period']
	 *
	 * @return array
	 */
	public function create($users) {
		$users = zbx_toArray($users);

		$this->checkInput($users, __FUNCTION__);

		$userids = DB::insert('users', $users);

		foreach ($users as $unum => $user) {
			$userid = $userids[$unum];

			$usrgrps = zbx_objectValues($user['usrgrps'], 'usrgrpid');
			foreach ($usrgrps as $groupid) {
				$usersGroupdId = get_dbid('users_groups', 'id');
				$sql = 'INSERT INTO users_groups (id,usrgrpid,userid) VALUES ('.zbx_dbstr($usersGroupdId).','.zbx_dbstr($groupid).','.zbx_dbstr($userid).')';

				if (!DBexecute($sql)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
				}
			}

			foreach ($user['user_medias'] as $mediaData) {
				$mediaid = get_dbid('media', 'mediaid');
				$sql = 'INSERT INTO media (mediaid,userid,mediatypeid,sendto,active,severity,period)'.
					' VALUES ('.zbx_dbstr($mediaid).','.zbx_dbstr($userid).','.zbx_dbstr($mediaData['mediatypeid']).','.
					zbx_dbstr($mediaData['sendto']).','.zbx_dbstr($mediaData['active']).','.zbx_dbstr($mediaData['severity']).','.
					zbx_dbstr($mediaData['period']).')';
				if (!DBexecute($sql)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
				}
			}
		}

		return ['userids' => $userids];
	}

	/**
	 * Update user.
	 *
	 * @param array  $users
	 * @param string $users['userid']
	 * @param string $users['name']
	 * @param string $users['surname']
	 * @param array  $users['alias']
	 * @param string $users['passwd']
	 * @param string $users['url']
	 * @param int    $users['autologin']
	 * @param int    $users['autologout']
	 * @param string $users['lang']
	 * @param string $users['theme']
	 * @param int    $users['refresh']
	 * @param int    $users['rows_per_page']
	 * @param int    $users['type']
	 * @param array  $users['user_medias']
	 * @param string $users['user_medias']['mediatypeid']
	 * @param string $users['user_medias']['address']
	 * @param int    $users['user_medias']['severity']
	 * @param int    $users['user_medias']['active']
	 * @param string $users['user_medias']['period']
	 *
	 * @return array
	 */
	public function update($users) {
		$users = zbx_toArray($users);
		$userids = zbx_objectValues($users, 'userid');

		$this->checkInput($users, __FUNCTION__);

		foreach ($users as $user) {
			$self = (bccomp(self::$userData['userid'], $user['userid']) == 0);

			$result = DB::update('users', [
				[
					'values' => $user,
					'where' => ['userid' => $user['userid']]
				]
			]);

			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
			}

			if (isset($user['usrgrps']) && !is_null($user['usrgrps'])) {
				$newUsrgrpids = zbx_objectValues($user['usrgrps'], 'usrgrpid');

				// deleting all relations with groups, but not touching those, where user still must be after update
				DBexecute(
					'DELETE FROM users_groups'.
					' WHERE userid='.zbx_dbstr($user['userid']).
						' AND '.dbConditionInt('usrgrpid', $newUsrgrpids, true)
				);

				// getting the list of groups user is currently in
				$dbGroupsUserIn = DBSelect('SELECT usrgrpid FROM users_groups WHERE userid='.zbx_dbstr($user['userid']));
				$groupsUserIn = [];
				while ($grp = DBfetch($dbGroupsUserIn)) {
					$groupsUserIn[$grp['usrgrpid']] = $grp['usrgrpid'];
				}

				$usrgrps = API::UserGroup()->get([
					'usrgrpids' => zbx_objectValues($user['usrgrps'], 'usrgrpid'),
					'output' => API_OUTPUT_EXTEND,
					'preservekeys' => true
				]);
				foreach ($usrgrps as $groupid => $group) {
					// if user is not already in a given group
					if (isset($groupsUserIn[$groupid])) {
						continue;
					}

					$usersGroupdId = get_dbid('users_groups', 'id');
					$sql = 'INSERT INTO users_groups (id,usrgrpid,userid) VALUES ('.zbx_dbstr($usersGroupdId).','.zbx_dbstr($groupid).','.zbx_dbstr($user['userid']).')';

					if (!DBexecute($sql)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
					}
				}
			}
		}

		return ['userids' => $userids];
	}

	public function updateProfile($user) {
		$user['userid'] = self::$userData['userid'];

		return $this->update([$user]);
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array $userids
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateDelete(array $userids) {
		if (!$userids) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$this->checkPermissions($userids);
		$this->checkDeleteCurrentUser($userids);
		$this->checkDeleteInternal($userids);

		// Check if deleted users have a map.
		$user_maps = API::Map()->get([
			'output' => ['name', 'userid'],
			'userids' => $userids
		]);

		if ($user_maps) {
			// Get first problem user and map.
			$user_map = reset($user_maps);

			$db_users = $this->get([
				'output' => ['alias'],
				'userids' => [$user_map['userid']],
				'limit' => 1
			]);

			// Get first problem user.
			$db_user = reset($db_users);

			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('User "%1$s" is map "%2$s" owner.', $db_user['alias'], $user_map['name'])
			);
		}

		// Check if deleted users have a screen.
		$user_screens = API::Screen()->get([
			'output' => ['name', 'userid'],
			'userids' => $userids
		]);

		if ($user_screens) {
			// Get first problem user and screen.
			$user_screen = reset($user_screens);

			$db_users = $this->get([
				'output' => ['alias'],
				'userids' => [$user_screen['userid']],
				'limit' => 1
			]);

			// Get first problem user.
			$db_user = reset($db_users);

			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('User "%1$s" is screen "%2$s" owner.', $db_user['alias'], $user_screen['name'])
			);
		}

		// Check if deleted users have a slide show.
		$user_slideshow = DBfetch(DBselect(
			'SELECT s.name,s.userid'.
			' FROM slideshows s'.
			' WHERE '.dbConditionInt('s.userid', $userids)
		));

		if ($user_slideshow) {
			$db_users = $this->get([
				'output' => ['alias'],
				'userids' => [$user_slideshow['userid']],
				'limit' => 1
			]);

			// Get first problem user.
			$db_user = reset($db_users);

			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('User "%1$s" is slide show "%2$s" owner.', $db_user['alias'], $user_slideshow['name'])
			);
		}
	}

	/**
	 * Delete user.
	 *
	 * @param array $userids
	 *
	 * @return array
	 */
	public function delete(array $userids) {
		$this->validateDelete($userids);

		// Get users for audit log.
		$db_users = API::User()->get([
			'output' => ['alias', 'name', 'surname'],
			'userids' => $userids
		]);

		// Delete action operation msg.
		$db_operations = DBFetchArray(DBselect(
			'SELECT DISTINCT om.operationid'.
			' FROM opmessage_usr om'.
			' WHERE '.dbConditionInt('om.userid', $userids)
		));

		DB::delete('opmessage_usr', ['userid' => $userids]);

		// Delete empty operations.
		$del_operations = DBFetchArray(DBselect(
			'SELECT DISTINCT o.operationid,o.actionid'.
			' FROM operations o'.
			' WHERE '.dbConditionInt('o.operationid', zbx_objectValues($db_operations, 'operationid')).
				' AND NOT EXISTS(SELECT NULL FROM opmessage_grp omg WHERE omg.operationid=o.operationid)'.
				' AND NOT EXISTS(SELECT NULL FROM opmessage_usr omu WHERE omu.operationid=o.operationid)'
		));

		DB::delete('operations', ['operationid' => zbx_objectValues($del_operations, 'operationid')]);
		DB::delete('media', ['userid' => $userids]);
		DB::delete('profiles', ['userid' => $userids]);
		DB::delete('users_groups', ['userid' => $userids]);
		DB::delete('users', ['userid' => $userids]);

		$actionids = zbx_objectValues($del_operations, 'actionid');
		if ($actionids) {
			$this->disableActionsWithoutOperations($actionids);
		}

		// Audit log.
		foreach ($db_users as $db_user) {
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER,
				'User alias ['.$db_user['alias'].'] name ['.$db_user['name'].'] surname ['.$db_user['surname'].']'
			);
		}

		return ['userids' => $userids];
	}

	/**
	 * Add user media.
	 *
	 * @param array  $data['users']
	 * @param string $data['users']['userid']
	 * @param array  $data['medias']
	 * @param string $data['medias']['mediatypeid']
	 * @param string $data['medias']['address']
	 * @param int    $data['medias']['severity']
	 * @param int    $data['medias']['active']
	 * @param string $data['medias']['period']
	 *
	 * @return array
	 */
	public function addMedia(array $data) {
		$this->validateAddMedia($data);
		$mediaIds = $this->addMediaReal($data);

		return ['mediaids' => $mediaIds];
	}

	/**
	 * Validate add user media.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array  $data['users']
	 * @param string $data['users']['userid']
	 * @param array  $data['medias']
	 * @param string $data['medias']['mediatypeid']
	 * @param string $data['medias']['address']
	 * @param int    $data['medias']['severity']
	 * @param int    $data['medias']['active']
	 * @param string $data['medias']['period']
	 */
	protected function validateAddMedia(array $data) {
		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Only Zabbix Admins can add user media.'));
		}

		if (!isset($data['users']) || !isset($data['medias'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
		}

		$users = zbx_toArray($data['users']);
		$media = zbx_toArray($data['medias']);

		if (!$this->isWritable(zbx_objectValues($users, 'userid'))) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$mediaDBfields = [
			'period' => null,
			'mediatypeid' => null,
			'sendto' => null,
			'active' => null,
			'severity' => null
		];

		foreach ($media as $mediaItem) {
			if (!check_db_fields($mediaDBfields, $mediaItem)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
		}

		$timePeriodValidator = new CTimePeriodValidator();

		foreach ($media as $mediaItem) {
			if (!$timePeriodValidator->validate($mediaItem['period'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $timePeriodValidator->getError());
			}
		}
	}

	/**
	 * Create user media.
	 *
	 * @throws APIException if user media insert is fail.
	 *
	 * @param array  $data['users']
	 * @param string $data['users']['userid']
	 * @param array  $data['medias']
	 * @param string $data['medias']['mediatypeid']
	 * @param string $data['medias']['address']
	 * @param int    $data['medias']['severity']
	 * @param int    $data['medias']['active']
	 * @param string $data['medias']['period']
	 *
	 * @return array
	 */
	protected function addMediaReal(array $data) {
		$users = zbx_toArray($data['users']);
		$media = zbx_toArray($data['medias']);

		$mediaIds = [];

		foreach ($users as $user) {
			foreach ($media as $mediaItem) {
				$mediaId = get_dbid('media', 'mediaid');

				$sql = 'INSERT INTO media (mediaid,userid,mediatypeid,sendto,active,severity,period)'.
						' VALUES ('.zbx_dbstr($mediaId).','.zbx_dbstr($user['userid']).','.zbx_dbstr($mediaItem['mediatypeid']).','.
									zbx_dbstr($mediaItem['sendto']).','.zbx_dbstr($mediaItem['active']).','.zbx_dbstr($mediaItem['severity']).','.
									zbx_dbstr($mediaItem['period']).')';

				if (!DBexecute($sql)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot insert user media.'));
				}

				$mediaIds[] = $mediaId;
			}
		}

		return $mediaIds;
	}

	/**
	 * Update user media.
	 *
	 * @throws APIException if user media update is fail.
	 *
	 * @param array  $data['users']
	 * @param string $data['users']['userid']
	 * @param array  $data['medias']
	 * @param string $data['medias']['mediatypeid']
	 * @param string $data['medias']['address']
	 * @param int    $data['medias']['severity']
	 * @param int    $data['medias']['active']
	 * @param string $data['medias']['period']
	 *
	 * @return array
	 */
	public function updateMedia(array $data) {
		$this->validateUpdateMedia($data);

		$users = zbx_toArray($data['users']);
		$media = zbx_toArray($data['medias']);

		$userIds = array_keys(array_flip((zbx_objectValues($users, 'userid'))));

		$dbMedia = API::UserMedia()->get([
			'output' => ['mediaid'],
			'userids' => $userIds,
			'editable' => true,
			'preservekeys' => true
		]);

		$mediaToCreate = $mediaToUpdate = $mediaToDelete = [];

		foreach ($media as $mediaItem) {
			if (isset($mediaItem['mediaid'])) {
				$mediaToUpdate[$mediaItem['mediaid']] = $mediaItem;
			}
			else {
				$mediaToCreate[] = $mediaItem;
			}
		}

		foreach ($dbMedia as $dbMediaItem) {
			if (!isset($mediaToUpdate[$dbMediaItem['mediaid']])) {
				$mediaToDelete[$dbMediaItem['mediaid']] = $dbMediaItem['mediaid'];
			}
		}

		// create
		if ($mediaToCreate) {
			$this->addMediaReal([
				'users' => $users,
				'medias' => $mediaToCreate
			]);
		}

		// update
		if ($mediaToUpdate) {
			foreach ($mediaToUpdate as $media) {
				$result = DBexecute(
					'UPDATE media'.
					' SET mediatypeid='.zbx_dbstr($media['mediatypeid']).','.
						' sendto='.zbx_dbstr($media['sendto']).','.
						' active='.zbx_dbstr($media['active']).','.
						' severity='.zbx_dbstr($media['severity']).','.
						' period='.zbx_dbstr($media['period']).
					' WHERE mediaid='.zbx_dbstr($media['mediaid'])
				);

				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot update user media.'));
				}
			}
		}

		// delete
		if ($mediaToDelete) {
			$this->deleteMediaReal($mediaToDelete);
		}

		return ['userids' => $userIds];
	}

	/**
	 * Validate update user media.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array  $data['users']
	 * @param string $data['users']['userid']
	 * @param array  $data['medias']
	 * @param string $data['medias']['mediatypeid']
	 * @param string $data['medias']['address']
	 * @param int    $data['medias']['severity']
	 * @param int    $data['medias']['active']
	 * @param string $data['medias']['period']
	 */
	protected function validateUpdateMedia(array $data) {
		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Zabbix Admins can change user media.'));
		}

		if (!isset($data['users']) || !isset($data['medias'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
		}

		$users = zbx_toArray($data['users']);
		$media = zbx_toArray($data['medias']);

		// validate user permissions
		if (!$this->isWritable(zbx_objectValues($users, 'userid'))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		// validate media permissions
		$mediaIds = [];

		foreach ($media as $mediaItem) {
			if (isset($mediaItem['mediaid'])) {
				$mediaIds[$mediaItem['mediaid']] = $mediaItem['mediaid'];
			}
		}

		if ($mediaIds) {
			$dbUserMediaCount = API::UserMedia()->get([
				'countOutput' => true,
				'mediaids' => $mediaIds,
				'editable' => true
			]);

			if ($dbUserMediaCount != count($mediaIds)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		// validate media parameters
		$mediaDBfields = [
			'period' => null,
			'mediatypeid' => null,
			'sendto' => null,
			'active' => null,
			'severity' => null
		];

		$timePeriodValidator = new CTimePeriodValidator();

		foreach ($media as $mediaItem) {
			if (!check_db_fields($mediaDBfields, $mediaItem)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}

			if (!$timePeriodValidator->validate($mediaItem['period'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $timePeriodValidator->getError());
			}
		}
	}

	/**
	 * Delete user media.
	 *
	 * @param array $mediaIds
	 *
	 * @return array
	 */
	public function deleteMedia($mediaIds) {
		$mediaIds = zbx_toArray($mediaIds);

		$this->validateDeleteMedia($mediaIds);
		$this->deleteMediaReal($mediaIds);

		return ['mediaids' => $mediaIds];
	}

	/**
	 * Validate delete user media.
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateDeleteMedia(array $mediaIds) {
		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Only Zabbix Admins can remove user media.'));
		}

		$dbUserMediaCount = API::UserMedia()->get([
			'countOutput' => true,
			'mediaids' => $mediaIds,
			'editable' => true
		]);

		if (count($mediaIds) != $dbUserMediaCount) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Delete user media.
	 *
	 * @throws APIException if delete is fail
	 */
	public function deleteMediaReal($mediaIds) {
		if (!DBexecute('DELETE FROM media WHERE '.dbConditionInt('mediaid', $mediaIds))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete user media.'));
		}
	}

	/**
	 * Authenticate a user using LDAP.
	 *
	 * The $user array must have the following attributes:
	 * - user       - user name
	 * - password   - user password
	 *
	 * @param array $user
	 *
	 * @return bool
	 */
	protected function ldapLogin(array $user) {
		$config = select_config();
		$cnf = [];

		foreach ($config as $id => $value) {
			if (strpos($id, 'ldap_') !== false) {
				$cnf[str_replace('ldap_', '', $id)] = $config[$id];
			}
		}

		if (!function_exists('ldap_connect')) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Probably php-ldap module is missing.'));
		}

		$ldapValidator = new CLdapAuthValidator(['conf' => $cnf]);

		if ($ldapValidator->validate($user)) {
			return true;
		}
		else {
			self::exception(ZBX_API_ERROR_PARAMETERS, '登录名称或密码是错误的.');
		}
	}

	private function dbLogin($user) {
		$login = DBfetch(DBselect(
			'SELECT NULL'.
			' FROM users u'.
			' WHERE u.alias='.zbx_dbstr($user['user']).
				' AND u.passwd='.zbx_dbstr(md5($user['password']))
		));

		if ($login) {
			return true;
		}
		else {
			self::exception(ZBX_API_ERROR_PARAMETERS, '登录名称或密码是错误的.');
		}
	}

	public function logout() {
		$sessionId = CWebUser::$data['sessionid'];

		$session = DBfetch(DBselect(
			'SELECT s.userid'.
			' FROM sessions s'.
			' WHERE s.sessionid='.zbx_dbstr($sessionId).
				' AND s.status='.ZBX_SESSION_ACTIVE
		));

		if (!$session) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot logout.'));
		}

		DBexecute('DELETE FROM sessions WHERE status='.ZBX_SESSION_PASSIVE.' AND userid='.zbx_dbstr($session['userid']));
		DBexecute('UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.' WHERE sessionid='.zbx_dbstr($sessionId));

		return true;
	}

	/**
	 * Login user.
	 *
	 * @param array $user
	 * @param array $user['user']		User alias
	 * @param array $user['password']	User password
	 *
	 * @return string					session id
	 */
	public function login($user) {
		$name = $user['user'];
		$password = md5($user['password']);

		$userInfo = DBfetch(DBselect(
			'SELECT u.userid,u.attempt_failed,u.attempt_clock,u.attempt_ip'.
			' FROM users u'.
			' WHERE u.alias='.zbx_dbstr($name)
		));
		if (!$userInfo) {
			self::exception(ZBX_API_ERROR_PARAMETERS, '登录名称或密码是错误的.');
		}

		// check if user is blocked
		if ($userInfo['attempt_failed'] >= ZBX_LOGIN_ATTEMPTS) {
			if ((time() - $userInfo['attempt_clock']) < ZBX_LOGIN_BLOCK) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Account is blocked for %s seconds', (ZBX_LOGIN_BLOCK - (time() - $userInfo['attempt_clock']))));
			}

			DBexecute('UPDATE users SET attempt_clock='.time().' WHERE alias='.zbx_dbstr($name));
		}

		// check system permissions
		if (!check_perm2system($userInfo['userid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions for system access.'));
		}

		$dbAccess = DBfetch(DBselect(
			'SELECT MAX(g.gui_access) AS gui_access'.
			' FROM usrgrp g,users_groups ug'.
			' WHERE ug.userid='.zbx_dbstr($userInfo['userid']).
				' AND g.usrgrpid=ug.usrgrpid'
		));

		if (zbx_empty($dbAccess['gui_access'])) {
			$guiAccess = GROUP_GUI_ACCESS_SYSTEM;
		}
		else {
			$guiAccess = $dbAccess['gui_access'];
		}

		$config = select_config();
		$authType = $config['authentication_type'];

		switch ($guiAccess) {
			case GROUP_GUI_ACCESS_INTERNAL:
				$authType = ($authType == ZBX_AUTH_HTTP) ? ZBX_AUTH_HTTP : ZBX_AUTH_INTERNAL;
				break;
			case GROUP_GUI_ACCESS_DISABLED:
				/* fall through */
			case GROUP_GUI_ACCESS_SYSTEM:
				/* fall through */
		}

		if ($authType == ZBX_AUTH_HTTP) {
			// if PHP_AUTH_USER is not set, it means that HTTP authentication is not enabled
			if (!isset($_SERVER['PHP_AUTH_USER'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot login.'));
			}
			// check if the user name used when calling the API matches the one used for HTTP authentication
			elseif ($name !== $_SERVER['PHP_AUTH_USER']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Login name "%1$s" does not match the name "%2$s" used to pass HTTP authentication.',
						$name, $_SERVER['PHP_AUTH_USER']
					)
				);
			}
		}

		try {
			switch ($authType) {
				case ZBX_AUTH_LDAP:
					$this->ldapLogin($user);
					break;
				case ZBX_AUTH_INTERNAL:
					$this->dbLogin($user);
					break;
				case ZBX_AUTH_HTTP:
			}
		}
		catch (APIException $e) {
			$ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))
					? $_SERVER['HTTP_X_FORWARDED_FOR']
					: $_SERVER['REMOTE_ADDR'];
			$userInfo['attempt_failed']++;

			DBexecute(
				'UPDATE users'.
				' SET attempt_failed='.zbx_dbstr($userInfo['attempt_failed']).','.
					' attempt_clock='.time().','.
					' attempt_ip='.zbx_dbstr($ip).
				' WHERE userid='.zbx_dbstr($userInfo['userid'])
			);

			add_audit_details(AUDIT_ACTION_LOGIN, AUDIT_RESOURCE_USER, $userInfo['userid'], '',
				_s('Login failed "%s".', $name), $userInfo['userid']
			);
			self::exception(ZBX_API_ERROR_PARAMETERS, $e->getMessage());
		}

		// start session
		$sessionid = md5(time().$password.$name.rand(0, 10000000));
		DBexecute('INSERT INTO sessions (sessionid,userid,lastaccess,status)'.
			' VALUES ('.zbx_dbstr($sessionid).','.zbx_dbstr($userInfo['userid']).','.time().','.ZBX_SESSION_ACTIVE.')'
		);

		$userData = $this->_getUserData($userInfo['userid']);
		$userData['sessionid'] = $sessionid;
		$userData['gui_access'] = $guiAccess;
		$userData['userid'] = $userInfo['userid'];

		if ($userInfo['attempt_failed']) {
			DBexecute('UPDATE users SET attempt_failed=0 WHERE userid='.zbx_dbstr($userInfo['userid']));
		}

		CWebUser::$data = self::$userData = $userData;

		return isset($user['userData']) ? $userData : $userData['sessionid'];
	}

	/**
	 * Check if session id is authenticated.
	 *
	 * @param array $sessionid		session id
	 *
	 * @return array				an array of user data
	 */
	public function checkAuthentication(array $sessionid) {
		$sessionid = reset($sessionid);

		// access DB only once per page load
		if (!is_null(self::$userData)) {
			return self::$userData;
		}

		$time = time();

		$userInfo = DBfetch(DBselect(
			'SELECT u.userid,u.autologout,s.lastaccess'.
			' FROM sessions s,users u'.
			' WHERE s.sessionid='.zbx_dbstr($sessionid).
				' AND s.status='.ZBX_SESSION_ACTIVE.
				' AND s.userid=u.userid'.
				' AND (s.lastaccess+u.autologout>'.$time.' OR u.autologout=0)'
		));

		if (!$userInfo) {
			self::exception(ZBX_API_ERROR_PARAMETERS, '会话终止，请重新登录。');
		}

		// don't check permissions on the same second
		if ($time != $userInfo['lastaccess']) {
			if (!check_perm2system($userInfo['userid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions for system access.'));
			}

			if ($userInfo['autologout'] > 0) {
				DBexecute(
					'DELETE FROM sessions'.
					' WHERE userid='.zbx_dbstr($userInfo['userid']).
						' AND lastaccess<'.(time() - $userInfo['autologout'])
				);
			}

			DBexecute(
				'UPDATE sessions'.
				' SET lastaccess='.time().
				' WHERE userid='.zbx_dbstr($userInfo['userid']).
					' AND sessionid='.zbx_dbstr($sessionid)
			);
		}

		$dbAccess = DBfetch(DBselect(
			'SELECT MAX(g.gui_access) AS gui_access'.
			' FROM usrgrp g,users_groups ug'.
			' WHERE ug.userid='.zbx_dbstr($userInfo['userid']).
				' AND g.usrgrpid=ug.usrgrpid'
		));

		if (!zbx_empty($dbAccess['gui_access'])) {
			$guiAccess = $dbAccess['gui_access'];
		}
		else {
			$guiAccess = GROUP_GUI_ACCESS_SYSTEM;
		}

		$userData = $this->_getUserData($userInfo['userid']);
		$userData['sessionid'] = $sessionid;
		$userData['gui_access'] = $guiAccess;

		CWebUser::$data = self::$userData = $userData;

		return $userData;
	}

	private function _getUserData($userid) {
		$userData = DBfetch(DBselect(
			'SELECT u.userid,u.alias,u.name,u.surname,u.url,u.autologin,u.autologout,u.lang,u.refresh,u.type,'.
			' u.theme,u.attempt_failed,u.attempt_ip,u.attempt_clock,u.rows_per_page'.
			' FROM users u'.
			' WHERE u.userid='.zbx_dbstr($userid)
		));

		$userData['debug_mode'] = (bool) DBfetch(DBselect(
			'SELECT ug.userid'.
			' FROM usrgrp g,users_groups ug'.
			' WHERE ug.userid='.zbx_dbstr($userid).
				' AND g.usrgrpid=ug.usrgrpid'.
				' AND g.debug_mode='.GROUP_DEBUG_MODE_ENABLED
		));

		$userData['userip'] = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'])
			? $_SERVER['HTTP_X_FORWARDED_FOR']
			: $_SERVER['REMOTE_ADDR'];

		return $userData;
	}

	public function isReadable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get([
			'userids' => $ids,
			'countOutput' => true
		]);

		return (count($ids) == $count);
	}

	public function isWritable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get([
			'userids' => $ids,
			'editable' => true,
			'countOutput' => true
		]);

		return (count($ids) == $count);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$userIds = zbx_objectValues($result, 'userid');

		// adding usergroups
		if ($options['selectUsrgrps'] !== null && $options['selectUsrgrps'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'userid', 'usrgrpid', 'users_groups');

			$dbUserGroups = API::UserGroup()->get([
				'output' => $options['selectUsrgrps'],
				'usrgrpids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);

			$result = $relationMap->mapMany($result, $dbUserGroups, 'usrgrps');
		}

		// adding medias
		if ($options['selectMedias'] !== null && $options['selectMedias'] != API_OUTPUT_COUNT) {
			$userMedias = API::UserMedia()->get([
				'output' => $this->outputExtend($options['selectMedias'], ['userid', 'mediaid']),
				'userids' => $userIds,
				'preservekeys' => true
			]);

			$relationMap = $this->createRelationMap($userMedias, 'userid', 'mediaid');

			$userMedias = $this->unsetExtraFields($userMedias, ['userid', 'mediaid'], $options['selectMedias']);
			$result = $relationMap->mapMany($result, $userMedias, 'medias');
		}

		// adding media types
		if ($options['selectMediatypes'] !== null && $options['selectMediatypes'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'userid', 'mediatypeid', 'media');
			$mediaTypes = API::Mediatype()->get([
				'output' => $options['selectMediatypes'],
				'mediatypeids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $mediaTypes, 'mediatypes');
		}

		return $result;
	}

	/**
	 * Checks if the given users are editable.
	 *
	 * @param array $userIds	user ids to check
	 *
	 * @throws APIException		if the user has no permissions to edit users or a user does not exist
	 */
	protected function checkPermissions(array $userIds) {
		if (!$this->isWritable($userIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Check if we're trying to delete the currently logged in user.
	 *
	 * @param array $userIds	user ids to check
	 *
	 * @throws APIException		if we're deleting the current user
	 */
	protected function checkDeleteCurrentUser(array $userIds) {
		if (in_array(self::$userData['userid'], $userIds)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('User is not allowed to delete himself.'));
		}
	}

	/**
	 * Check if we're trying to delete the guest user.
	 *
	 * @param array $userIds	user ids to check
	 *
	 * @throws APIException		if we're deleting the guest user
	 */
	protected function checkDeleteInternal(array $userIds) {
		$guest = $this->get([
			'output' => ['userid'],
			'filter' => [
				'alias' => ZBX_GUEST_USER
			]
		]);
		$guest = reset($guest);

		if (in_array($guest['userid'], $userIds)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Cannot delete Zabbix internal user "%1$s", try disabling that user.', ZBX_GUEST_USER)
			);
		}
	}

	/**
	 * Disable actions that do not have operations.
	 */
	protected function disableActionsWithoutOperations(array $actionids) {
		$actions = DBFetchArray(DBselect(
			'SELECT DISTINCT a.actionid'.
			' FROM actions a'.
			' WHERE NOT EXISTS (SELECT NULL FROM operations o WHERE o.actionid=a.actionid)'.
				' AND '.dbConditionInt('a.actionid', $actionids)
		));

		$actions_without_operations = zbx_objectValues($actions, 'actionid');
		if ($actions_without_operations) {
			$this->disableActions($actions_without_operations);
		}
	}

	/**
	 * Disable actions.
	 *
	 * @param array $actionids
	 */
	protected function disableActions(array $actionids) {
		$update = [
			'values' => ['status' => ACTION_STATUS_DISABLED],
			'where' => ['actionid' => $actionids]
		];
		DB::update('actions', $update);

		foreach($actionids as $actionid) {
			add_audit_details(AUDIT_ACTION_DISABLE, AUDIT_RESOURCE_ACTION, $actionid, '',
				_('Action disabled due to deletion of user.'), null
			);
		}
	}
}
