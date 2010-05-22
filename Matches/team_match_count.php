<?php
	// always be careful with $_SERVER['PHP_SELF']) because custom links can change the original page
	if (preg_match("/team_match_count/i", $_SERVER['PHP_SELF']))
	{
		die("This file is meant to be only included by other files!");
	}
	
   
    // backend functions
    function increase_total_match_count($teamid, $site, $connection)
    {
        $query = 'UPDATE `teams_profile` SET ';
        $query .= '`num_matches_played`=`num_matches_played`+' . sqlSafeStringQuotes('1');
        $query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($teamid) . ')';
        // only one team needs to be updated
        $query .= ' LIMIT 1';
        if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
        {
            unlock_tables($site, $connection);
            $site->dieAndEndPage('Could not update win/play count for team with id ' . sqlSafeString($teamid) . ' due to a sql problem!');
        }
    }
    
    function increase_won_match_count($teamid, $site, $connection)
    {
        $query = 'UPDATE `teams_profile` SET ';
        $query .= '`num_matches_won`=`num_matches_won`+' . sqlSafeStringQuotes('1');
        $query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($teamid) . ')';
        // only one team needs to be updated
        $query .= ' LIMIT 1';
        if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
        {
            unlock_tables($site, $connection);
            $site->dieAndEndPage('Could not update win/play count for team with id ' . sqlSafeString($teamid) . ' due to a sql problem!');
        }
    }    
    
    function increase_lost_match_count($teamid, $site, $connection)
    {
        $query = 'UPDATE `teams_profile` SET ';
        $query .= '`num_matches_lost`=`num_matches_lost`+' . sqlSafeStringQuotes('1');
        $query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($teamid) . ')';
        // only one team needs to be updated
        $query .= ' LIMIT 1';
        if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
        {
            unlock_tables($site, $connection);
            $site->dieAndEndPage('Could not update win/play count for team with id ' . sqlSafeString($teamid) . ' due to a sql problem!');
        }
    }
    
    function increase_draw_match_count($teamid, $site, $connection)
    {
        $query = 'UPDATE `teams_profile` SET ';
        $query .= '`num_matches_draw`=`num_matches_draw`+' . sqlSafeStringQuotes('1');
        $query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($teamid) . ')';
        // only one team needs to be updated
        $query .= ' LIMIT 1';
        if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
        {
            unlock_tables($site, $connection);
            $site->dieAndEndPage('Could not update win/play count for team with id ' . sqlSafeString($teamid) . ' due to a sql problem!');
        }
    }
    
    function decrease_total_match_count($teamid, $site, $connection)
    {
        $query = 'UPDATE `teams_profile` SET ';
        $query .= '`num_matches_played`=`num_matches_played`-' . sqlSafeStringQuotes('1');
        $query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($teamid) . ')';
        // only one team needs to be updated
        $query .= ' LIMIT 1';
        if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
        {
            unlock_tables($site, $connection);
            $site->dieAndEndPage('Could not update win/play count for team with id ' . sqlSafeString($teamid) . ' due to a sql problem!');
        }
    }
    
    function decrease_won_match_count($teamid, $site, $connection)
    {
        $query = 'UPDATE `teams_profile` SET ';
        $query .= '`num_matches_won`=`num_matches_won`-' . sqlSafeStringQuotes('1');
        $query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($teamid) . ')';
        // only one team needs to be updated
        $query .= ' LIMIT 1';
        if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
        {
            unlock_tables($site, $connection);
            $site->dieAndEndPage('Could not update win/play count for team with id ' . sqlSafeString($teamid) . ' due to a sql problem!');
        }
    }
    
    function decrease_lost_match_count($teamid, $site, $connection)
    {
        $query = 'UPDATE `teams_profile` SET ';
        $query .= '`num_matches_lost`=`num_matches_lost`-' . sqlSafeStringQuotes('1');
        $query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($teamid) . ')';
        // only one team needs to be updated
        $query .= ' LIMIT 1';
        if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
        {
            unlock_tables($site, $connection);
            $site->dieAndEndPage('Could not update win/play count for team with id ' . sqlSafeString($teamid) . ' due to a sql problem!');
        }
    }
    
    function decrease_draw_match_count($teamid, $site, $connection)
    {
        $query = 'UPDATE `teams_profile` SET ';
        $query .= '`num_matches_draw`=`num_matches_draw`-' . sqlSafeStringQuotes('1');
        $query .= ' WHERE (`teamid`=' . sqlSafeStringQuotes($teamid) . ')';
        // only one team needs to be updated
        $query .= ' LIMIT 1';
        if (!($result = $site->execute_query($site->db_used_name(), 'teams_profile', $query, $connection)))
        {
            unlock_tables($site, $connection);
            $site->dieAndEndPage('Could not update win/play count for team with id ' . sqlSafeString($teamid) . ' due to a sql problem!');
        }
    }
    
    function cmp_did_team_participated_at_all($team1_points_before, $team2_points_before,
                                              $team1_points, $team2_points,
                                              $team_id1_before, $team_id2_before,
                                              $team_id1, $team_id2,
                                              $site, $connection)
    {
        // check if old team1 is still active in the new match version
        
        if (($team_id1_before !== $team_id1) && ($team_id1_before !== $team_id2))
        {
            // old team1 did participate in the older match version but not in the new version
            decrease_total_match_count($team_id1_before, $site, $connection);
            // new team1 played a match not counted yet
            increase_total_match_count($team_id1, $site, $connection);
            
            // update old team1 data
            if ($team1_points_before > $team2_points_before)
            {
                // old team1 won in the older version
                decrease_won_match_count($team_id1_before, $site, $connection);
            } else
            {
                if ($team1_points_before < $team2_points_before)
                {
                    // old team1 lost in the older version
                    decrease_lost_match_count($team_id1_before, $site, $connection);
                } else
                {
                    // old team1 tied in the older version
                    decrease_draw_match_count($team_id1_before, $site, $connection);
                }
            }
            
            // update new team1 data
            if ($team1_points > $team2_points)
            {
                // new team1 won
                increase_won_match_count($team_id1, $site, $connection);
            } else
            {
                if ($team1_points < $team2_points)
                {
                    // new team1 lost
                    increase_lost_match_count($team_id1, $site, $connection);
                } else
                {
                    // new team1 tied
                    increase_draw_match_count($team_id1, $site, $connection);
                }
            }
        }
    }
    
    function cmp_team_participated_change($team1_points_before, $team2_points_before,
                                          $team1_points, $team2_points,
                                          $team_id1_before, $team_id2_before,
                                          $team_id1, $team_id2,
                                          $site, $connection)
    {        
        // map old team id to new team id
        if ($team_id1_before === $team_id1)
        {
            if ($site->debug_sql())
            {
                echo '<br><p>teamid ' . htmlentities($team_id1) . ' mapped</p>';
                
                echo '<hr>' . "\n";
                echo '<p>Function cmp_team_participated_change called.</p>' . "\n";
                echo '<p>$team_id1_before: ' . htmlentities($team_id1_before) . '</p>' . "\n";
                echo '<p>$team_id1: ' . htmlentities($team_id1) . '</p>' . "\n";
                
                echo '<p>$team1_points_before: ' . htmlentities($team1_points_before) . '</p>' . "\n";
                echo '<p>$team2_points_before: ' . htmlentities($team2_points_before) . '</p>' . "\n";
                echo '<p>$team1_points: ' . htmlentities($team1_points) . '</p>' . "\n";
                echo '<p>$team2_points: ' . htmlentities($team2_points) . '</p>' . "\n";            
                echo '<hr>' . "\n";
                
            }
            if ($team1_points_before > $team2_points_before)
            {
                // team1 won in the older version
                if ($team1_points > $team2_points)
                {
                    // team1 won also in the newer version -> nothing to do for team1
                    
                    // team1 winning the newer match has team2 loosing the newer match as consequence
                    if (!($team_id2_before === $team_id2))
                    {
                        // team2 did not participate in older version
                        increase_total_match_count($team_id2, $site, $connection);
                        increase_lost_match_count($team_id2, $site, $connection);
                        
                        // following case already handled by function cmp_team_participated_at_all
                        // old team2 lost in older version but is not involved in newer version
                        // decrease_total_match_count($team_id2_before, $site, $connection);
                        // decrease_lost_match_count($team_id2_before, $site, $connection);
                    }
                } else
                {
                    if ($team1_points < $team2_points)
                    {
                        // team1 lost in the newer version but won in the older version
                        decrease_won_match_count($team_id1, $site, $connection);
                        increase_lost_match_count($team_id1, $site, $connection);
                        
                        if ($team_id2_before === $team_id2)
                        {
                            // team2 also participated in the older version 
                            
                            // team2 lost in the older version but won in the newer version
                            decrease_lost_match_count($team_id2, $site, $connection);
                            increase_won_match_count($team_id2, $site, $connection);
                        } else
                        {
                            // team2 did not participate in older version
                            increase_total_match_count($team_id2, $site, $connection);
                            increase_won_match_count($team_id2, $site, $connection);
                            
                            // following case already handled by function cmp_team_participated_at_all
                            // old team2 won in older version but is not involved in newer version
                            // decrease_total_match_count($team_id2_before, $site, $connection);
                            // decrease_won_match_count($team_id2_before, $site, $connection);
                        }
                    } else
                    {
                        // team1 tied in the newer version but won in the older version
                        decrease_draw_match_count($team_id1, $site, $connection);
                        increase_won_match_count($team_id1, $site, $connection);
                        
                        if ($team_id2_before === $team_id2)
                        {
                            // team2 also participated in the older version 
                            
                            // team2 lost in the older version but tied in the newer version
                            0($team_id2, $site, $connection);
                            increase_draw_match_count($team_id2, $site, $connection);
                        } else
                        {
                            // team2 did not participate in older version
                            increase_total_match_count($team_id2, $site, $connection);
                            increase_draw_match_count($team_id2, $site, $connection);
                            
                            // following case already handled by function cmp_team_participated_at_all
                            // old team2 lost in older version but is not involved in newer version
                            // decrease_total_match_count($team_id2_before, $site, $connection);
                            // decrease_lost_match_count($team_id2_before, $site, $connection);                            
                        }
                    }
                }
            } else
            {
                if ($team1_points_before < $team2_points_before)
                {
                    // team1 lost in the older match version
                    if ($team1_points > $team2_points)
                    {
                        // team1 won in the newer version
                        decrease_lost_match_count($team_id1, $site, $connection);
                        increase_won_match_count($team_id1, $site, $connection);
                        
                        // team1 loosing the newer match has team2 winning the newer match as consequence
                        if (!($team_id2_before === $team_id2))
                        {
                            // team2 did not participate in older version
                            increase_total_match_count($team_id2, $site, $connection);
                            increase_won_match_count($team_id2, $site, $connection);
                            
                            // following case already handled by function cmp_team_participated_at_all
                            // old team2 lost in older version but is not involved in newer version
                            // decrease_total_match_count($team_id2_before, $site, $connection);
                            // decrease_lost_match_count($team_id2_before, $site, $connection);
                        }
                    } else
                    {
                        if ($team1_points < $team2_points)
                        {
                            // team1 lost in the older version and in the newer version
                            
                            if (!($team_id2_before === $team_id2))
                            {
                                // team2 did not participate in older version
                                increase_total_match_count($team_id2, $site, $connection);
                                increase_won_match_count($team_id2, $site, $connection);
                            }
                        } else
                        {
                            // team1 lost in the older version but tied in the newer version
                            decrease_lost_match_count($team_id1, $site, $connection);
                            increase_draw_match_count($team_id1, $site, $connection);
                            
                            if (!($team_id2_before === $team_id2))
                            {
                                // team2 did not participate in older version
                                increase_total_match_count($team_id2, $site, $connection);
                                increase_draw_match_count($team_id2, $site, $connection);
                                
                                // following case already handled by function cmp_team_participated_at_all
                                // old team2 won in older version but is not involved in newer version
                                // decrease_total_match_count($team_id2_before, $site, $connection);
                                // decrease_won_match_count($team_id2_before, $site, $connection);                                
                            }
                        }
                    }
                } else
                {
                    // team1 tied in the older match version
                    
                    if ($team1_points > $team2_points)
                    {
                        // team1 won in the newer version
                        decrease_draw_match_count($team_id1, $site, $connection);
                        increase_won_match_count($team_id1, $site, $connection);
                        
                        // team1 drawing the newer match has team2 drawing the newer match as consequence
                        if (!($team_id2_before === $team_id2))
                        {
                            // team2 did not participate in older version
                            increase_total_match_count($team_id2, $site, $connection);
                            increase_draw_match_count($team_id2, $site, $connection);
                            
                            // following case already handled by function cmp_team_participated_at_all
                            // old team2 lost in older version but is not involved in newer version
                            // decrease_total_match_count($team_id2_before, $site, $connection);
                            // decrease_draw_match_count($team_id2_before, $site, $connection);
                        }
                    } else
                    {
                        if ($team1_points < $team2_points)
                        {
                            // team1 tied in the older version and lost in the newer version
                            
                            if (!($team_id2_before === $team_id2))
                            {
                                // team2 did not participate in older version
                                increase_total_match_count($team_id2, $site, $connection);
                                increase_won_match_count($team_id2, $site, $connection);
                            }
                        } else
                        {
                            // team1 tied in the older version and also tied in the newer version -> nothing to do
                            
                            if (!($team_id2_before === $team_id2))
                            {
                                // team2 did not participate in older version
                                increase_total_match_count($team_id2, $site, $connection);
                                increase_draw_match_count($team_id2, $site, $connection);
                                
                                // following case already handled by function cmp_team_participated_at_all
                                // old team2 won in older version but is not involved in newer version
                                // decrease_total_match_count($team_id2_before, $site, $connection);
                                // decrease_draw_match_count($team_id2_before, $site, $connection);                                
                            }
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }
        
    function update_team_match_edit($team1_points_before, $team2_points_before,
                                    $team1_points, $team2_points,
                                    $team_id1_before, $team_id2_before,
                                    $team_id1, $team_id2,
                                    $site, $connection)
	{
        if ($site->debug_sql())
        {
            echo '<hr>' . "\n";
            echo '<p>Updating win, draw, loose count of teams (edit case).</p>' . "\n";
            echo '<p>$team1_points_before: ' . htmlentities($team1_points_before) . '</p>' . "\n";
            echo '<p>$team2_points_before: ' . htmlentities($team2_points_before) . '</p>' . "\n";
            echo '<p>$team1_points: ' . htmlentities($team1_points) . '</p>' . "\n";
            echo '<p>$team2_points: ' . htmlentities($team2_points) . '</p>' . "\n";
            echo '<p>$team_id1_before: ' . htmlentities($team_id1_before) . '</p>' . "\n";
            echo '<p>$team_id2_before: ' . htmlentities($team_id2_before) . '</p>' . "\n";
            echo '<p>$team_id1: ' . htmlentities($team_id1) . '</p>' . "\n";
            echo '<p>$team_id2: ' . htmlentities($team_id2) . '</p>' . "\n";
            echo '<hr>' . "\n";
        }
        
        // check if old team1 is still active in the new match version
        cmp_did_team_participated_at_all($team1_points_before, $team2_points_before,
                                         $team1_points, $team2_points,
                                         $team_id1_before, $team_id2_before,
                                         $team_id1, $team_id2,
                                         $site, $connection);
        // swap the team orders to apply the same algorithm to old team2
        cmp_did_team_participated_at_all($team2_points_before, $team1_points_before,
                                         $team2_points, $team1_points,
                                         $team_id2_before, $team_id1_before,
                                         $team_id1, $team_id2,
                                         $site, $connection);
        
        // update match stats for team1 in case old team1 = new team1
        
        $number_teams_mapped = (int) 0;
        echo "call1";
        if (cmp_team_participated_change($team1_points_before, $team2_points_before,
                                         $team1_points, $team2_points,
                                         $team_id1_before, $team_id2_before,
                                         $team_id1, $team_id2,
                                         $site, $connection))
        {
            $number_teams_mapped = $number_teams_mapped + 1;
        }
        
        echo "call2";
        if (cmp_team_participated_change($team2_points_before, $team1_points_before,
                                         $team1_points, $team2_points,
                                         $team_id2_before, $team_id1_before,
                                         $team_id1, $team_id2,
                                         $site, $connection))
        {
            $number_teams_mapped = $number_teams_mapped + 1;
        }
        
        if (!($number_teams_mapped > 2))
        {
            echo "call3";
            if (cmp_team_participated_change($team1_points_before, $team2_points_before,
                                             $team1_points, $team2_points,
                                             $team_id1_before, $team_id2_before,
                                             $team_id1, $team_id2,
                                             $site, $connection))
            {
                $number_teams_mapped = $number_teams_mapped + 1;
            }
            if (!($number_teams_mapped > 2))
            {
                echo "call4";
                if (cmp_team_participated_change($team1_points_before, $team2_points_before,
                                                 $team2_points, $team1_points,
                                                 $team_id1_before, $team_id2_before,
                                                 $team_id2, $team_id1,
                                                 $site, $connection))
                {
                    $number_teams_mapped = $number_teams_mapped + 1;
                }
            }
        }
        unset($number_teams_mapped);
    }
?>
