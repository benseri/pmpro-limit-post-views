<?php
/*
Plugin Name: PMPro Limit Post Views
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-limit-post-views/
Description: Integrates with Paid Memberships Pro to limit the number of times non-members can view posts on your site.
Version: .2
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/
/*
	The Plan
	- Track a cookie on the user's computer.
	- Only track on pages the user doesn't have access to.
	- Allow up to 4 views without a membership level.
	- On 4th view each month, redirect to a specific page to get them to sign up.
*/
define('PMPRO_LPV_LIMIT', 2);			//<-- how many posts can a user view per month
define('PMPRO_LPV_USE_JAVASCRIPT', false);

//php limit (deactivate JS version below if you use this)
add_action("wp", "pmpro_lpv_wp");
function pmpro_lpv_wp()
{	
	if(function_exists("pmpro_has_membership_access"))
	{
		/*
			If we're viewing a page that the user doesn't have access to...
			Could add extra checks here.
		*/
		if(!pmpro_has_membership_access())
		{
			//ignore non-posts
			global $post;	
			if($post->post_type != "post")
				return;
			
			//if we're using javascript, just give them access and let JS redirect them
			if(PMPRO_LPV_USE_JAVASCRIPT)
			{			
				wp_enqueue_script('wp-utils', includes_url('/js/utils.js'));
				add_action("wp_footer", "pmpro_lpv_wp_footer");
				add_filter("pmpro_has_membership_access_filter", "__return_true");
				return;
			}
			
			//PHP is going to handle cookie check and redirect
			$thismonth = date("n");
		
			//check for past views
			if(!empty($_COOKIE['pmpro_lpv_ids']))
			{
				$parts = explode(",", $_COOKIE['pmpro_lpv_ids']);				
				$month = $parts[0];
				$post_ids = array_slice($parts, 1);
				if($month == $thismonth)
				{
					if (!in_array(strval($post->ID), $post_ids)) {
						array_push($post_ids, strval($post->ID));
					}
				}
				else
				{
					$post_ids = array($post->ID);
					$month = $thismonth;
				}				
			}
			else
			{
				//new user
				$post_ids = array($post->ID);
				$month = $thismonth;
			}

			$count = count($post_ids);
			
			//if count is not above limit, allow access and update cookie
			if($count <= PMPRO_LPV_LIMIT)
			{
				//give them access and track the view
				add_filter("pmpro_has_membership_access_filter", "__return_true");
				setcookie("pmpro_lpv_ids", $month . "," . implode(",", $post_ids), time()+3600*24*31, "/");
			}
		}				
	}
}

/*
	javascript limit (hooks for these are above)
	this is only loaded on pages that are locked for members
*/
function pmpro_lpv_wp_footer()
{	
	?>
	<script>		
		function inArray(needle, haystack) {
			var length = haystack.length;
			for(var i = 0; i < length; i++) {
				if(haystack[i] == needle) return true;
			}
			return false;
		}
		//vars
		var pmpro_lpv_ids_cookie;	//stores cookie
		var parts;					//cookie convert to array of 2 parts
		var count;					
		var month;					//part 0 is the month
		var post_ids;				//part 1 is the viewed ids		
		
		//what is the current month?
		var d = new Date();
		var thismonth = d.getMonth();
		
		//get cookie
		pmpro_lpv_ids_cookie = wpCookies.get('pmpro_lpv_ids');
		
		if(pmpro_lpv_ids_cookie)
		{
			//get values from cookie
			parts = pmpro_lpv_ids_cookie.split(',');
			month = parts[0];
			post_ids = parts.slice(1);
			post_id = String(<?php echo $post->ID; ?>);
			
			if(month == thismonth)
			{
				if (!inArray(post_id, post_ids)) {
						post_ids.push(post_id);
				}
			}
			else
			{
				post_ids = [post_id];
				month = thismonth;
			}	
		}
		else
		{
			//defaults
			post_ids = [post_id];
			month = thismonth;
		}				
		
		count = post_ids.length;
		
		//if count is above limit, redirect, otherwise update cookie
		if(count > <?php echo intval(PMPRO_LPV_LIMIT); ?>)
		{
			window.location.replace('<?php echo pmpro_url("levels");?>');				
		}
		else
		{
			//track the view
			wpCookies.set('pmpro_lpv_ids', String(month) + ',' + pmpro_lpv_ids.join(',') , 3600*24*60, '/');				
		}
	</script>
	<?php
}
