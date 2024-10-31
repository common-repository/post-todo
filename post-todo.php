<?php
/*
Plugin Name: Post Todo List
Plugin URI: http://jameslow.com/2008/12/02/post-todo/
Description: Create multiple todo lists, one for each post or page if you want.
Version: 1.1.4
Author: James Low
Author URI: http://jameslow.com
*/

include_once( dirname(__FILE__) . '/../../../wp-load.php' );

//Global constants
define('post_todo', 'post_todo_');
define('post_todo_dir', get_settings('siteurl').'/wp-content/plugins/post-todo');
define('post_todo_url', get_settings('siteurl').'/wp-content/plugins/post-todo/post-todo.php');
define('post_todo_tag', '[post_todo]');
define('post_todo_tag_all', '[post_todo_all]');

//Option constants
define('post_todo_meta', post_todo.'meta');
define('post_todo_option', post_todo.'option_');
define('post_todo_option_maxid', post_todo_option.'maxid');
define('post_todo_option_minlevel', post_todo_option.'minlevel');
define('post_todo_option_style', post_todo_option.'style');
define('post_todo_option_redirect', post_todo_option.'redirect');

//Columns constants
define('post_todo_id', 'id');
define('post_todo_user_id', 'user_id');
define('post_todo_priority', 'priority');
define('post_todo_complete', 'complete');
define('post_todo_category', 'category');
define('post_todo_content', 'content');
define('post_todo_duedata', 'duedate');

//Form constants
define('post_todo_sort', post_todo.'sort');
define('post_todo_edit', post_todo.'edit');
define('post_todo_delete', post_todo.'delete');
define('post_todo_submit_post', post_todo.'submit');
define('post_todo_submit_add', 'add');
define('post_todo_submit_update', 'update');
define('post_todo_submit_delete', 'delete');
define('post_todo_redirect_post', post_todo.'redirect');
define('post_todo_post_id_post', post_todo.'post_id');
define('post_todo_id_post', post_todo.post_todo_id);
define('post_todo_user_id_post', post_todo.post_todo_user_id);
define('post_todo_priority_post', post_todo.post_todo_priority);
define('post_todo_complete_post', post_todo.post_todo_complete);
define('post_todo_category_post', post_todo.post_todo_category);
define('post_todo_content_post', post_todo.post_todo_content);
define('post_todo_duedata_post', post_todo.post_todo_duedata);

//Priority constants
define('post_todo_priority_none', '0');
define('post_todo_priority_low', '1');
define('post_todo_priority_medium', '2');
define('post_todo_priority_high', '3');
$post_todo_priorities[post_todo_priority_none] = 'None';
$post_todo_priorities[post_todo_priority_low] = 'Low';
$post_todo_priorities[post_todo_priority_medium] = 'Medium';
$post_todo_priorities[post_todo_priority_high] = 'High';

$submit = $_REQUEST[post_todo_submit_post];
$delete = $_REQUEST[post_todo_delete];
if (isset($submit) || isset($delete)) {
	if (posttodo_canedit()) {
		$post_id = $_REQUEST[post_todo_post_id_post];
		$todolist = new posttodolist($post_id);
		if (post_todo_submit_add == $submit) {
			$todolist->addNewTodo($_REQUEST[post_todo_content_post.'_add'], $_REQUEST[post_todo_category_post.'_add'], $_REQUEST[post_todo_user_id_post.'_add'], $_REQUEST[post_todo_priority_post.'_add'], isset($_REQUEST[post_todo_complete_post.'_add']), $_REQUEST[post_todo_duedata_post.'_add']);
		} elseif (post_todo_submit_delete == $submit) {
			$todolist->deleteTodo($_REQUEST[post_todo_id_post]);
		} elseif (post_todo_submit_update == $submit) {
			$ids = $_REQUEST[post_todo_id_post];
			$completes = $_REQUEST[post_todo_complete_post];
			$user_ids = $_REQUEST[post_todo_user_id_post];
			$duedates = $_REQUEST[post_todo_duedata_post];
			$contents = $_REQUEST[post_todo_content_post];
			$categories = $_REQUEST[post_todo_category_post];
			$priorities = $_REQUEST[post_todo_priority_post];
			$i=0;
			foreach($ids as $id) {
				$found = false;
				if (is_array($completes)) {
					foreach ($completes as $complete) {
						if ($complete == $id) {
							$found = true;
							break;
						}
					}
				}
				$todolist->updateTodo($id,$todolist->newTodo($contents[$i], $categories[$i], $user_ids[$i], $priorities[$i], $found, $duedates[$i]));
				$i++;
			}
		} elseif (isset($delete)) {
			$todolist->deleteTodo($delete);
		}
		$todolist->save();
	}
	$doredirect = get_option(post_todo_option_redirect);
	if ($doredirect) {
		$redirect = $_REQUEST[post_todo_redirect_post];
		if (!isset($redirect)) {
			$redirect = $_SERVER['HTTP_REFERER'];
			if (!isset($redirect)) {
			 echo "Updated.";
			}
		}
		header("Location: ".$redirect);
	}
}
if (!$doredirect) {
	add_filter('the_content', 'posttodo_filter', 1);
	add_filter('the_content_rss', 'posttodo_filter_rss', 1);
	add_filter('the_excerpt', 'posttodo_filter_remove', 1);
	add_filter('the_excerpt_rss', 'posttodo_filter_remove', 1);
	add_action('admin_menu', 'posttodo_addoptions');
}

function posttodo_gettodos() {
	$result = array();
	$posts = get_posts('numberposts=-1');
	foreach ($posts as $post) {
		$todolist = new posttodolist($post->ID);
		$result = array_merge($result,$todolist->getTodos());
	}
	return $result;
}

function posttodo_canedit() {
	global $userdata;
	include( dirname(__FILE__) . '/../../../wp-includes/pluggable.php' );
	get_currentuserinfo();
	if ($userdata->user_login) {
		$level = get_option(post_todo_option_minlevel);
		if ($userdata->user_level >= $level) {
			return true;	
		}
	}
	return false;
}

function posttodo_addoptions() {
	if (function_exists('add_options_page')) {
		add_options_page('Post ToDo', 'Post ToDo', 9, __FILE__, 'posttodo_options');
	}
}

function posttodo_option($level, $name, $select) {
	echo "<option value=\"$level\" " . ($select ? "selected" : "") . ">$name</option>";
}

function posttodo_subscriber($level) {
	return $level == 0;
}
function posttodo_contributor($level) {
	return $level == 1;
}
function posttodo_author($level) {
	return ($level >= 2 && $level <= 4);
}
function posttodo_editor($level) {
	return ($level >= 5 && $level <= 7);
}
function posttodo_admin($level) {
	return ($level >= 8 && $level <= 10);
}

function posttodo_options() {
	$level = get_option(post_todo_option_minlevel);
	$style = get_option(post_todo_option_style);
	$redirect = get_option(post_todo_option_redirect);
?>
<div class="wrap">
  <h2>Post ToDo Options</h2>
  Add a todo list to your post or page by entering the following tag [post_todo]
  <br />
  <br />Add a (read only) todo list that shows todos from all your posts using [post_todo_all]
  <br /><form action="options.php" method="post">
    <input type="hidden" name="action" value="update" />
    <input type="hidden" name="page_options" value="<?php echo post_todo_option_minlevel.','.post_todo_option_style.','.post_todo_option_redirect; ?>" />
    <?php if (function_exists('wp_nonce_field')): wp_nonce_field('update-options'); endif; ?>
    <table class="form-table">
      <tr valign="top">
        <th scope="row"><label for="<?php echo post_todo_option_minlevel ?>">Minimum Level</label></th>
        <td>
          <select name="<?php echo post_todo_option_minlevel ?>" id="<?php echo post_todo_option_minlevel ?>">
          <?php
          posttodo_option(0, "Subscriber", posttodo_subscriber($level));
          posttodo_option(1, "Contributor", posttodo_contributor($level));
          posttodo_option(2, "Author", posttodo_author($level));
          posttodo_option(5, "Editor", posttodo_editor($level));
          posttodo_option(10, "Admin", posttodo_admin($level));
          ?>
          </select>
          Miniumum level to edit todo list.<br />
        </td>
      </tr>
      <tr valign="top">
        <th scope="row"><label for="<?php echo post_todo_option_style ?>">Text Style</label></th>
        <td>
          <input type="text" name="<?php echo post_todo_option_style ?>" id="<?php echo post_todo_option_style ?>" value="<?php echo $style ?>" />
          Style for the border / background and text of the todo and category. Just enter raw CSS.<br />
        </td>
      </tr>
      <tr valign="top">
        <th scope="row"><label for="<?php echo post_todo_option_redirect ?>">Do redirect</label></th>
        <td>
          <input type="checkbox" name="<?php echo post_todo_option_redirect ?>" id="<?php echo post_todo_option_redirect ?>" <?php echo ($redirect ? 'checked="checked"' : '') ?> value="1" />
          Use redirect method for editing todos (not compatible with some wordpress setups/plugins).<br />
        </td>
      </tr>
      </table>
    <p class="submit">
      <input type="submit" name="Submit" value="Save Changes" />
    </p>
  </form>
</div>
<?php
}

function posttodo_getusers() {
	global $wpdb;
	$thisquery = "SELECT * FROM $wpdb->users A";
	$users = $wpdb->get_results($thisquery);
	return $users;
}

function posttodo_priority_option($level, $name, $select) {
	return "<option value=\"$level\" " . ($select == $level ? "selected" : "") . ">$name</option>";
}

function posttodo_priority_list($name, $select = 0, $extra = '') {
	global $post_todo_priorities;
	$result .= '<select name="'.$name.'" '.$extra.'>';
	foreach ($post_todo_priorities as $level => $value) {
		$result .= posttodo_priority_option($level,$value,$select);
	}
	$result .= '</select>';
	return $result;
}

function posttodo_user_option($user_id, $name, $select) {
	return "<option value=\"$user_id\" " . ($select == $user_id ? "selected" : "") . ">$name</option>";
}

function posttodo_user_list($users, $name, $select = -1, $extra = '') {
	$result .= '<select name="'.$name.'" '.$extra.'>';
	$result .= posttodo_user_option(-1,'',$select);
	foreach ($users as $user) {
		$result .= posttodo_user_option($user->ID,$user->user_nicename,$select);
	}
	$result .= '</select>';
	return $result;
}

function posttodo_filter($content) {
	return posttodo_addtodo($content);
}

function posttodo_sort($sort, $link = '', $show = true) {
	if ($show) {
		$delim = (strpos($link,'?') !== false ? '&' : '?');
		return ' <a href="'.$link.$delim.post_todo_sort.'='.$sort.(isset($_REQUEST[post_todo_edit]) ? '&'.post_todo_edit.'=1' : '').'"><img style="border:0px;padding:0px;" src="'.post_todo_dir.'/triangle.png" /></a>';
	} else {
		return '';
	}
}

function posttodo_addtodo($content, $show = true) {
	$all = strpos($content,post_todo_tag_all) !== false;
	if (strpos($content,post_todo_tag) !== false || $all) {
		global $post;
		$result = '';
		if (!$all) {
			$post_id = $post->ID;
			$todolist = new posttodolist($post_id);
		} else {
			$post_id = -1;
			$todolist = new posttodolist($post_id, posttodo_gettodos());
		}
		$canedit = posttodo_canedit() && $show && !$all;
		$shouldedit = $canedit && isset($_REQUEST[post_todo_edit]);
		$users = posttodo_getusers();
		$link = get_permalink();
		$disabled = (!$canedit ? ' disabled="disabled"' : '');
		$style = get_option(post_todo_option_style);
		if ($style == '') {
			$style = 'border:1px solid #000000;background:transparent;';
		}

		$result .= posttodo_javascript();
		$doredirect = get_option(post_todo_option_redirect);
		if ($doredirect) {
			$result .= '<form method="post" action="'.post_todo_url.'">';
		} else {
			$result .= '<form method="post" action="'.$link.'">';
		}
		$result .= '<input type="hidden" name="'.post_todo_post_id_post.'" value="'.$post_id.'" />';
		$result .= '<input type="hidden" name="'.post_todo_redirect_post.'" value="'.$link.'" />';

		$result .= '<table cellpadding="0" border="0"><tr><th>'.posttodo_sort(post_todo_complete,$link,$show).'</th><th>Category'.posttodo_sort(post_todo_category,$link,$show).'</th><th>Description'.posttodo_sort(post_todo_content,$link,$show).'</th><th>User'.posttodo_sort(post_todo_user_id,$link,$show).'</th><th>Priority'.posttodo_sort(post_todo_priority,$link,$show).'</th>'.($canedit ? '<th></th>' : '').'</tr>';
		if ($canedit) {
			$result .= '<tr><th><input type="checkbox" name="'.post_todo_complete_post.'_add" value="add" /></th><th><input type="text" style="'.$style.'" size="10" name="'.post_todo_category_post.'_add" /></th><th><input type="text" style="'.$style.'" name="'.post_todo_content_post.'_add" /></th><th>'.posttodo_user_list($users,post_todo_user_id_post.'_add').'</th><th>'.posttodo_priority_list(post_todo_priority_post.'_add').'</th><th><input type="image" name="'.post_todo_submit_post.'" value="add" src="'.post_todo_dir.'/plusoff.gif" width="13" height="13" border="0" /></th></tr>';
		}
		
		if ($all) {
			$todos = posttodo_gettodos();
		} else {
			$todos = $todolist->getTodos();
		}
		$sort = $_REQUEST[post_todo_sort];
		$todos = posttodo_php_multisort($todos, array(array('key'=>post_todo_complete),array('key'=>post_todo_category),array('key'=>post_todo_priority),array('key'=>post_todo_content)));
		if (isset($sort)) {
			$todos = posttodo_php_multisort($todos, array(array('key'=>$sort)));
		}
		foreach ($todos as $todo) {
			if ($todo[post_todo_user_id] != -1) {
				$user_info = get_userdata($todo[post_todo_user_id]);
			}
			$id = $todo[post_todo_id];
			$complete = $todo[post_todo_complete];
			$description = $todo[post_todo_content];
			$result .= '<tr><input type="hidden" name="'.post_todo_id_post.'[]" value="'.$id.'" />';
			$result .= '<td><input type="checkbox" name="'.post_todo_complete_post.'[]" value="'.$id.'" '.($complete ? ' checked="checked"' : '').$disabled.' /></td>';
			$result .= '<td><input type="text" size="10" style="'.$style.'" name="'.post_todo_category_post.'[]" value="'.$todo[post_todo_category].'"'.$disabled.' /></td>';
			if ($shouldedit) {
				$result .= '<td><input type="text" style="'.$style.($complete ? 'text-decoration: line-through;' : '').'" name="'.post_todo_content_post.'[]" value="'.htmlspecialchars($description).'"'.$disabled.' /></td>';
			} else {
				$result .= '<td><input type="hidden" name="'.post_todo_content_post.'[]" value="'.htmlspecialchars($description).'" />';
				$result .= ($all ? '<a href="'.get_permalink($id).'">' : '').'<span style="'.($complete ? 'text-decoration: line-through;' : '').'">'.$description.'</span></td>'.($all ? '</a>' : '');
			}
			$result .= '<td>'.posttodo_user_list($users,post_todo_user_id_post.'[]',$todo[post_todo_user_id],$disabled).'</td>';
			$result .= '<td>'.posttodo_priority_list(post_todo_priority_post.'[]',$todo[post_todo_priority],$disabled).'</td>';
			$result .= ($canedit ? '<td><input type="image" name="'.post_todo_delete.'" value="'.$id.'" src="'.post_todo_dir.'/crossoff.gif" width="13" height="13" border="0" /></td>' : '');
			//$href = post_todo_url.'?'.post_todo_submit_post.'='.post_todo_submit_delete.'&'.post_todo_post_id_post.'='.$post_id.'&'.post_todo_id_post.'='.$id.'&'.post_todo_redirect_post.'='.urlencode($link);
			//$result .= ($canedit ? '<td><a href="'.$href.'"><img style="border:0px;padding:0px;" name="cross'.$id.'" onload="cache_image(\'cross'.$id.'\',\''.post_todo_dir.'/crosson.gif\')" onMouseOut="button_off(\'cross'.$id.'\')" onMouseOver="button_on(\'cross'.$id.'\')" src="'.post_todo_dir.'/crossoff.gif" /></a></td>' : '');
			$result .= '</tr>';
		}
		$delim = (strpos($link,'?') !== false ? '&' : '?');
		$suffix = (isset($sort) ? $delim.post_todo_sort.'='.$sort : '');
		$suffixsuffix = $suffix.($suffix == '' ? $delim : '' ).post_todo_edit.'=1';
		$result .= '</table>'.($canedit ? '<div align="right"><table cellspacing="0" cellpadding="0" border="0"><tr><td>'.($shouldedit ? '<a href="'.$link.$suffix.'">Back</a>' : '<a href="'.$link.$suffixsuffix.'">Edit</a>').'</td> <td><input type="submit" value="update" name="'.post_todo_submit_post.'" /></td></tr></table></div>' : '').'</form>';
		if ($all) {
			return posttodo_replace($content,'',$result);
		} else {
			return posttodo_replace($content,$result);
		}
	}
	return $content;
}

function posttodo_javascript() {
	//TODO: Not sure why its replacing last too quotes
	return '<script>function cache_image ( imgName, src_on, src_off, width, height ) {
	eval ( imgName + "_on = new Image (" + width + ", "+ height + ");" );
	eval ( imgName + "_on.src = \"" + src_on + "\";" );
	eval ( imgName + "_off = new Image (" + width + ", "+ height + ");" );
	eval ( imgName + "_off.src = \"" + src_off + "\";" );
} function button_on ( imgName ) {
	butOn = eval ( imgName + "_on.src" );
	document [imgName].src = butOn;
} function button_off ( imgName ) {
	butOff = eval ( imgName + "_off.src" );
	document [imgName].src = butOff;
}</script>';
}

function posttodo_filter_rss($content) {
	return posttodo_addtodo($content, false);
}

function posttodo_filter_remove($content) {
	return posttodo_replace($content);
}

function posttodo_replace($content, $replace1 = '', $replace2 = '') {
	return str_replace(post_todo_tag_all,$replace2,str_replace(post_todo_tag,$replace1,$content));
}

function posttodo_setmaxid($max_id) {
	update_option(post_todo_option_maxid,$max_id);
}

function posttodo_getmaxid() {
	$maxid = get_option(post_todo_option_maxid);
	if (intval($maxid) >= 0) {
		return $maxid;
	} else {
		return 0;
	}
}

function posttodo_getnextid() {
	$maxid = posttodo_getmaxid();
	$maxid = $maxid + 1;
	posttodo_setmaxid($maxid);
	return $maxid;
}

function posttodo_php_multisort($data,$keys,$case=true) {
  if (is_array($data) && count($data) > 0) {
  // List As Columns
  foreach ($data as $key => $row) {
    foreach ($keys as $k){
      $cols[$k['key']][$key] = $row[$k['key']];
    }
  }
  // List original keys
  $idkeys=array_keys($data);
  // Sort Expression
  $i=0;
  foreach ($keys as $k){
    if($i>0){$sort.=',';}
    $sort.='$cols['.$k['key'].']';
    if($k['sort']){$sort.=',SORT_'.strtoupper($k['sort']);}
    if($k['type']){$sort.=',SORT_'.strtoupper($k['type']);}
    $i++;
  }
  $sort.=',$idkeys';
  // Sort Funct
  $sort='array_multisort('.$sort.');';
  eval($sort);
  // Rebuild Full Array
  foreach($idkeys as $idkey){
    $result[$idkey]=$data[$idkey];
  }
  return $result;
  }
  return array();
}

class posttodolist {
	function __construct ($post_id, $todoarray = null) {
		$this->post_id = $post_id;
		$this->todoarray = $todoarray;
		if (!is_array($this->todoarray)) {
			//$this->todolistarray = json_decode(get_post_meta($this->post_id, post_todo_meta, true));
			$this->todoarray = get_post_meta($this->post_id, post_todo_meta, true);
			if (!is_array($this->todoarray)) {
				$this->todoarray = array();
			}
		}
	}
	
	function addTodo($todo) {
		$todo[post_todo_id] = posttodo_getnextid();
		$this->todoarray[] = $todo;
	}
	
	function newTodo($content, $category = '', $user_id = -1, $priority = 0, $complete = false, $duedate = 0) {
		$todo = array();
		$todo[post_todo_content] = $content;
		$todo[post_todo_category] = $category;
		$todo[post_todo_user_id] = $user_id;
		$todo[post_todo_priority] = $priority;
		$todo[post_todo_complete] = $complete;
		$todo[post_todo_duedata] = $duedate;
		return $todo;
	}
	
	function addNewTodo($content, $category = '', $user_id = -1, $priority = 0, $complete = false, $duedate = 0) {
		$this->addTodo($this->newTodo($content, $category, $user_id, $priority, $complete, $duedate));
	}
	
	function getTodos($column = null, $value = null) {
		if (!isset($column) || $column == '') {
			return $this->todoarray;
		} else {
			$result = array();
			foreach($this->todoarray as $todo) {
				if ($todo[$column] == $value) {
					$result[] = $todo;
				}
			}
			return $result;
		}
	}

	function deleteTodo($todo_id) {
		$result = array();
		foreach($this->todoarray as $todo) {
			if ($todo[post_todo_id] != $todo_id) {
				$result[] = $todo;
			}
		}
		$this->todoarray = $result;
	}
	
	function updateTodo($todo_id, $newtodo) {
		$this->deleteTodo($todo_id);
		$newtodo[post_todo_id] = $todo_id;
		$this->todoarray[] = $newtodo;
	}
	
	function updateTodoColumn($todo_id, $column, $value) {
		foreach($this->todoarray as $todo) {
			if ($todo[post_todo_id]  == $todo_id) {
				$todo[$column] = $value;
				break;
			}
		}
	}
	
	function completeTodo($todo_id, $complete = true) {
		$this->updateTodoColumn($todo_id, post_todo_complete, $complete);
	}
	
	function save() {
		add_post_meta($this->post_id, post_todo_meta, $this->todoarray, true) or update_post_meta($this->post_id, post_todo_meta, $this->todoarray);
	}
}
?>