<?php 
/*
Plugin Name: Top Commenters Gravatar
Plugin URI: http://suhanto.net/top-commenters-gravatar-widget-wordpress/
Description: Display the gravatars of top commenters for your blog. The gravatars are displayed as widget that can be placed anywhere within your blog.
Author: Agus Suhanto
Version: 1.1
Author URI: http://suhanto.net/

Copyright 2010 Agus Suhanto (email : agus@suhanto.net)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// wordpress plugin action hook
add_action('plugins_loaded', 'top_commenters_gravatar_init');

// initialization function
global $top_commenters_gravatar;
function top_commenters_gravatar_init() {
   $top_commenters_gravatar = new top_commenters_gravatar();
}

/*
 * This is the namespace for the 'top_commenters_gravatar' plugin / widget.
 */
class top_commenters_gravatar {

   protected $_name = "Top Commenters Gravatar";
   protected $_folder;
   protected $_path;
   protected $_width = 350;
   protected $_height = 320;
   protected $_link = 'http://suhanto.net/top-commenters-gravatar-widget-wordpress/';
   
   /*
    * Constructor
    */
   function __construct() {
      $path = __FILE__;
      if (!$path) { $path = $_SERVER['PHP_SELF']; }
         $current_dir = dirname($path);
      $current_dir = str_replace('\\', '/', $current_dir);
      $current_dir = explode('/', $current_dir);
      $current_dir = end($current_dir);
      if (empty($current_dir) || !$current_dir)
         $current_dir = 'top-commenters-gravatar';
      $this->_folder = $current_dir;
      $this->_path = '/wp-content/plugins/' . $this->_folder . '/';

      $this->init();
   }
   
   /*
    * Initialization function, called by plugin_loaded action.
    */
   function init() {
      add_action('template_redirect', array(&$this, 'template_redirect'));
      add_filter("plugin_action_links_$plugin", array(&$this, 'link'));
      load_plugin_textdomain($this->_folder, false, $this->_folder);      
      
      if (!function_exists('register_sidebar_widget') || !function_exists('register_widget_control'))
         return;
      register_sidebar_widget($this->_name, array(&$this, "widget"));
      register_widget_control($this->_name, array(&$this, "control"), $this->_width, $this->_height);
   }
   
   /*
    * Inserts the style into the head section.
    */
   function template_redirect() {
      $options = get_option($this->_folder);
      $this->validate_options($options);
      
      if (!isset($options['use_style']) || $options['use_style'] != 'checked')
         wp_enqueue_style($this->_folder, $this->_path . 'style.css', null, '1.1');
   }
   
   /*
    * Options validation.
    */
   function validate_options(&$options) {
      if (!is_array($options)) {
         $options = array(
            'title' => 'TOP Commenters',
            'num_of_commenters' => '10', 
            'gravatar_width' => '46', 
            'range_count' => '1', 
            'range_type' => 'year', 
            'excluded_emails' => '',      
            'single_mode' => '',
            'single_mode_title' => 'Commenters for this post',  
            'single_mode_text' => 'Want your gravatar and link displayed here? Just <a href="#respond">leave a comment</a>',
            'use_style' => '',
            'link_to_us' => '');
      }
      
      // validations and defaults
      if (intval($options['num_of_commenters']) == 0) $options['num_of_commenters'] = '10';
      if (intval($options['gravatar_width']) == 0) $options['gravatar_width'] = '46';
      if (intval($options['range_count']) == 0) $options['range_count'] = '1';
   }
   
   /*
    * Called by register_sidebar_widget() function.
    * Rendering of the widget happens here.
    */
   function widget($args) {
      global $wpdb;
   
      extract($args);
   
      $options = get_option($this->_folder);
      $this->validate_options($options);
      $excluded_emails = array();
      if (!empty($options['excluded_emails'])) {
         $excluded_emails = explode(',', $options['excluded_emails']);
         for ($i = 0; $i < sizeof($excluded_emails); $i++) {
            $excluded_emails[$i] = "'" . trim($excluded_emails[$i]) . "'";
         }
      }
   
      if (is_single() && $options['single_mode'] == 'checked') {
         global $post;
         $sql = "SELECT DISTINCT comment_author, comment_author_email, comment_author_url from $wpdb->comments WHERE comment_approved= '1'
                 AND comment_type != 'pingback' AND user_id != 1 AND comment_post_id = $post->ID
                 ORDER BY comment_date DESC
                 LIMIT " . $options['num_of_commenters'];
      } else {
         $sql = "SELECT comment_author, comment_author_email, comment_author_url, count(1) as counter from $wpdb->comments WHERE comment_approved= '1'
                 AND comment_type != 'pingback'";
         if (!empty($excluded_emails))
            $sql .= " AND comment_author_email NOT IN (" . implode(",", $excluded_emails) . ")";
         if ($options['range_type'] != 'all time')
            $sql .= " AND date_sub(curdate(), interval '" . $options['range_count'] . "' " . $options['range_type'] . ") < comment_date";
   
         $sql .= " GROUP BY comment_author, comment_author_email, comment_author_url
                   ORDER BY counter DESC, comment_date DESC
                   LIMIT " . $options['num_of_commenters'];
      }
      
      //echo $sql; // open this comment if you ever want to know the SQL
      $commenters = $wpdb->get_results($sql);
   
      echo $before_widget;
      echo $before_title;
      echo is_single() && ($options['single_mode'] == 'checked') ? $options['single_mode_title'] : $options['title'];
      echo $after_title;
      
      echo '<div class="tcg-div">';
      if ($commenters) {
         foreach ($commenters as $commenter) {
            $counter = isset($commenter->counter) ? '(' . $commenter->counter . ')' : '';
            $author_has_url = !(empty($commenter->comment_author_url) || 'http://' == $commenter->comment_author_url);
            $url = '<a href="' . $commenter->comment_author_url . '" title="' . $commenter->comment_author . ' ' . $counter . '" rel="external nofollow" target="_blank">';
            echo '<div class="tcg-image">';
            echo $author_has_url ? $url : '<span title="' . $commenter->comment_author . ' ' . $counter . '">';
            echo get_avatar($commenter->comment_author_email, intval($options['gravatar_width']));
            echo $author_has_url ? '</a>' : '</span>';
            echo '</div>';
         }
      }
      if (is_single() && $options['single_mode'] == 'checked') {
         if ($post->comment_status == 'open') {
            echo '<div class="tcg-message">' . $options['single_mode_text'] . '</div>';
         } elseif ($post->comment_status == 'closed' && !$commenters)
            echo '<div class="tcg-message">' . __('Sorry, the comment form is closed at this time.', $this->_folder) . '</div>';          
      } elseif (!$commenters) {
         echo '<div class="tcg-message">' . __('There is no TOP commenters at this time.', $this->folder) . '</div>';
      }
      
      if ($options['link_to_us'] == 'checked') {
         echo '<div class="tcg-link"><a href="' . $this->_link . '" target="_blank">'. __('Get this widget for your own blog free!', $this->_folder) . '</a></div>';
      }
      echo '</div>';
            
      echo $after_widget; 
   }
   
   /*
    * Plugin control funtion, used by admin screen.
    */
   function control() {
      $options = get_option($this->_folder);
      $this->validate_options($options);
   
      if ($_POST[$this->_folder . '-submit']) {
         $options['title'] = htmlspecialchars(stripslashes($_POST[$this->_folder . '-title']));      
         $options['num_of_commenters'] = htmlspecialchars($_POST[$this->_folder . '-num_of_commenters']);
   	     $options['gravatar_width'] = htmlspecialchars($_POST[$this->_folder . '-gravatar_width']);
         $options['range_count'] = htmlspecialchars($_POST[$this->_folder . '-range_count']);
   	     $options['range_type'] = htmlspecialchars($_POST[$this->_folder . '-range_type']);
   	     $options['excluded_emails'] = htmlspecialchars(stripslashes($_POST[$this->_folder . '-excluded_emails']));
         $options['single_mode'] = htmlspecialchars($_POST[$this->_folder . '-single_mode']);
         $options['single_mode_title'] = htmlspecialchars(stripslashes($_POST[$this->_folder . '-single_mode_title']));
         $options['single_mode_text'] = stripslashes($_POST[$this->_folder . '-single_mode_text']);
         $options['use_style'] = htmlspecialchars($_POST[$this->_folder . '-use_style']);
         $options['link_to_us'] = htmlspecialchars($_POST[$this->_folder . '-link_to_us']);
         update_option($this->_folder, $options);
      }
?>
      <p>
         <label for="<?php echo($this->_folder) ?>-title"><?php _e('Title: ', $this->_folder); ?></label>
         <input type="text" id="<?php echo($this->_folder) ?>-title" name="<?php echo($this->_folder) ?>-title" value="<?php echo $options['title']; ?>" size="50"></input>
      </p>
      <p>
         <label for="<?php echo($this->_folder) ?>-num_of_commenters"><?php _e('Num. of commenters to display: ', $this->_folder); ?></label>
         <input type="text" id="<?php echo($this->_folder) ?>-num_of_commenters" name="<?php echo($this->_folder) ?>-num_of_commenters" value="<?php echo $options['num_of_commenters']; ?>" size="2"></input> (<?php _e('default 10', $this->_folder) ?>) (<a href="<?php echo $this->_link?>#num-of-commenters" target="_blank">?</a>)
      </p>
      <p>
         <label for="<?php echo($this->_folder) ?>-gravatar_width"><?php _e('Gravatar width: ', $this->_folder); ?></label>
         <input type="text" id="<?php echo($this->_folder) ?>-gravatar_width" name="<?php echo($this->_folder) ?>-gravatar_width" value="<?php echo $options['gravatar_width']; ?>" size="2"></input>px (<?php _e('default 46', $this->_folder) ?>) (<a href="<?php echo $this->_link?>#gravatar-width" target="_blank">?</a>) 
      </p>
      <p>
         <label for="<?php echo($this->_folder) ?>-range_count"><?php _e('Include all commenters within: ', $this->_folder); ?> </label>
         <input type="text" id="<?php echo($this->_folder) ?>-range_count" name="<?php echo($this->_folder) ?>-range_count" value="<?php echo $options['range_count'];?>" size="1" />
         <select id="<?php echo($this->_folder) ?>-range_type" name="<?php echo($this->_folder) ?>-range_type">
   	       <option value="all time" <?php echo $options['range_type'] == 'all time' ? 'selected="true"' : ''; ?>><?php _e('all time', $this->_folder) ?></option>
   	       <option value="year" <?php echo $options['range_type'] == 'year' ? 'selected="true"' : ''; ?>><?php _e('year(s)', $this->_folder) ?></option>
   	       <option value="month" <?php echo $options['range_type'] == 'month' ? 'selected="true"' : ''; ?>><?php _e('month(s)', $this->_folder) ?></option>
   	       <option value="week" <?php echo $options['range_type'] == 'week' ? 'selected="true"' : ''; ?>><?php _e('week(s)', $this->_folder) ?></option>
   	     </select> (<a href="<?php echo $this->_link?>#commenters-range" target="_blank">?</a>)
      </p>
      <p>
         <label for="<?php echo($this->_folder) ?>-excluded_emails"><?php _e('Excluded emails (comma separated): ', $this->_folder); ?></label> (<a href="<?php echo $this->_link?>#excluded-emails" target="_blank">?</a>)
         <input type="text" id="<?php echo($this->_folder) ?>-excluded_emails" name="<?php echo($this->_folder) ?>-excluded_emails" value="<?php echo $options['excluded_emails']; ?>" size="50"></input>
      </p>
      <p>
          <input type="checkbox" id="<?php echo($this->_folder) ?>-single_mode" name="<?php echo($this->_folder) ?>-single_mode" value="checked" <?php echo $options['single_mode'];?> /> <?php _e('Use single mode in post', $this->_folder) ?> (<a href="<?php echo $this->_link?>#single-mode" target="_blank">?</a>)       
      </p>
      <p>
         <label for="<?php echo($this->_folder) ?>-single_mode_title"><?php _e('Title in single mode: ', $this->_folder); ?></label> (<a href="<?php echo $this->_link?>#single-mode-title" target="_blank">?</a>)
         <input type="text" id="<?php echo($this->_folder) ?>-single_mode_title" name="<?php echo($this->_folder) ?>-single_mode_title" value='<?php echo $options['single_mode_title']; ?>' size="50"></input>
      </p>
      <p>
         <label for="<?php echo($this->_folder) ?>-single_mode_text"><?php _e('Text in single mode: ', $this->_folder); ?></label> (<a href="<?php echo $this->_link?>#single-mode-text" target="_blank">?</a>)
         <input type="text" id="<?php echo($this->_folder) ?>-single_mode_text" name="<?php echo($this->_folder) ?>-single_mode_text" value='<?php echo $options['single_mode_text']; ?>' size="50"></input>
      </p>
      <p>
          <input type="checkbox" id="<?php echo($this->_folder) ?>-use_style" name="<?php echo($this->_folder) ?>-use_style" value="checked" <?php echo $options['use_style'];?> /> <?php _e('Use custom style', $this->_folder) ?> (<a href="<?php echo $this->_link?>#custom-style" target="_blank">?</a>) 
      </p>
      <p>
          <input type="checkbox" id="<?php echo($this->_folder) ?>-link_to_us" name="<?php echo($this->_folder) ?>-link_to_us" value="checked" <?php echo $options['link_to_us'];?> /> <?php _e('Link to us (optional)', $this->_folder) ?> (<a href="<?php echo $this->_link?>#link-to-us" target="_blank">?</a>) 
      </p>
      <p><?php printf(__('More details about these options, visit <a href="%s" target="_blank">Plugin Home</a>', $this->_folder), $this->_link) ?></p>
      <input type="hidden" id="<?php echo($this->_folder) ?>-submit" name="<?php echo($this->_folder) ?>-submit" value="1" />
<?php
   } 
   
   /*
    * Add extra link to widget list.
    */
   function link($links) {
      $options_link = '<a href="' . $this->_link . '">' . __('Donate', $this->_folder) . '</a>';
      array_unshift($links, $options_link);
      return $links;
   }

}

?>