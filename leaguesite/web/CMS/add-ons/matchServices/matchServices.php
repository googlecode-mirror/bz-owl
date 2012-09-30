<?php
	namespace matchServices;
	
	class matchServices extends \database
	{
		private $noGUI = false;
		
		public function __construct($title, $path)
		{
			// assume this add-on will not be directly called by user if no path is given
			if (strlen($path) === 0)
			{
				$this->noGUI = true;
			}
			
			// initialise root database class
			\database::__construct();
		}
		
		
		public function enterMatch($matchData, $matchDataFormat=1)
		{
			// enter a match
			// returns true if operation completed successfully
			// $matchData must be an associative array, its content structure is determined by $matchDataFormat
			// NOTE: Ignores auth, make sure to check permission before calling this function
			
			// Specification:
			// $matchDataFormat=1;
			// $matchData = array('timestamp' => 'YYYY:MM:DD HH:MM:SS', 'duration' => (int) 30,'team1ID' => (int) 1, 'team2ID' => (int) 2, 'team1Score' => (int) 3, 'team2Score' => (int) 4);
			// where team1ID and team2ID are unique id's from team table and both id's must not be identical
			// timestamp must be in UTC zone
			
			// call private function that deals with specific matchDataFormat 
			if (function_exists(enterMatch . $matchDataFormat))
			{
				// pass through the result
				return enterMatch . $matchDataFormat($matchData);
			}
			
			
			return false;
		}
		
		private function enterMatch1($matchData)
		{
			// check if all keys are set in $matchData
			if (!isset($matchData['timestamp']) || !isset($matchData['duration']) || !isset($matchData['team1ID'])
				|| !isset($matchData['team2ID']) || !isset($matchData['team1Score']) || !isset($matchData['team2Score']))
			{
				return 'E_REQ_PARAMS_NOT_SET';
			}
			
			
			// a match may not be played in the future -> final result not known 
			$curTime = (int) strtotime('now');
			if ((((int) $matchData['timestamp']) - $curTime) >= 0)
			{
				return 'E_TIMESTAMP_IN_FUTURE';
			}
			
			// a team may not play against itself
			if ($matchData['teamID1'] == $matchData['teamID2'])
			{
				return 'E_IDENTICAL_TEAMS';
			}
			
			// begin critical database part
			$this->SQL('LOCK TABLES `matches` WRITE');
			
			// get the score of both teams at the time we want to insert the new match
			$team1TotalScore = getTeamScore($matchData['team1ID'], $matchData['timestamp']);
			$team2TotalScore = getTeamScore($matchData['team2ID'], $matchData['timestamp']);
			
			
			$this->SQL('UNLOCK TABLES');
			return true;
		}
		
		
		private function getTeamScore($teamID, $timestamp='9999:99:99 99:99:99')
		{
			global $config;
			
			
			// 1200 is default starting value for all teams
			$defaultScore = 1200;
			
			// if a different default value is specified in settings, us that custom one
			$configValue = $config->getValue('cms.addon.matchServices.teamStartingScore');
			$score = isset($configValue) && $configValue ? $configValue : $defaultScore;
			
			$query = $this->prepare('SELECT * FROM `matches` WHERE `timestamp` <= \':timestamp\' AND (`team1_id`=\':teamID\' OR `team2_id`=\':teamID\')LIMIT 1');
			if (!$this->execute($query, array(':timestamp' => array($timestamp, PDO::PARAM_STR), ':teamID' => array($teamID, PDO::PARAM_INT))))
			{
				return 'E_TEAM_SCORE_QUERY_FAILURE';
			}
			
			$row = $this->fetchRow($query);
			$this->free($query);
			
			if (!$row)
			{
				// no match played
				return $score;
			}
			
			if ((int) $row['team1_id'] === $teamID)
			{
				$score = $row['team1_new_score'];
			} else
			{
				$score = $row['team2_new_score'];
			}
			
			return $score;
		}
	}
?>
