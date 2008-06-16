<?php include('header.php'); ?>

<div class="container navigator">
	<span class="older pct10"><a href="#" onclick="timeline.skipLoupeLeft();return false">&laquo; <?php _e('Older'); ?></a></span>
	<span class="currentposition pct15 minor"><?php _e('0 of 0'); ?></span>
	<span class="search pct50">
		<input id="search" type="search" placeholder="<?php _e('Type and wait to search for any entry component'); ?>" autosave="habaricontent" results="10" value="<?php echo $search_args ?>">
	</span>
	<span class="nothing pct15">&nbsp;</span>
	<span class="newer pct10"><a href="#" onclick="timeline.skipLoupeRight();return false"><?php _e('Newer'); ?> &raquo;</a></span>

	<div class="special_search pct100 minor">
		<?php foreach($special_searches as $text => $term): ?>
		<a href="#<?php echo $term; ?>"><?php echo $text; ?></a>
		<?php endforeach; ?>
	</div>

	<div class="timeline">
		<div class="years">
			<?php $theme->display( 'timeline_items' )?>
		</div>

		<div class="track">
			<div class="handle">
				<span class="resizehandleleft"></span>
				<span class="resizehandleright"></span>
			</div>
		</div>

	</div>

</div>

<form method="post" name="moderation" action="<?php URL::out( 'admin', array( 'page' => 'comments', 'status' => $status ) ); ?>">
	<input type="hidden" name="search" value="<?php echo $search; ?>">
	<input type="hidden" name="status" value="<?php echo $status; ?>">
	<input type="hidden" id="nonce" name="nonce" value="<?php echo $wsse['nonce']; ?>">
	<input type="hidden" id="timestamp" name="timestamp" value="<?php echo $wsse['timestamp']; ?>">
	<input type="hidden" id="PasswordDigest" name="PasswordDigest" value="<?php echo $wsse['digest']; ?>">

<div class="container transparent item comments controls">
	<span class="checkboxandselected pct25">
		<input type="checkbox">
		<span class="selectedtext minor none"><?php _e('None selected'); ?></span>
	</span>
	<span class="buttons">
		<input type="submit" name="do_approve" value="<?php _e('Approve'); ?>" class="approve button" onclick="itemManage.update( 'approve' ); return false;">
		<input type="submit" name="do_unapprove" value="<?php _e('Unapprove'); ?>" class="unapprove button" onclick="itemManage.update( 'unapprove' ); return false;">
		<input type="submit" name="do_spam" value="<?php _e('Spam'); ?>" class="spam button" onclick="itemManage.update( 'spam' ); return false;">
		<input type="submit" name="do_delete" value="<?php _e('Delete'); ?>" class="delete button" onclick="itemManage.update( 'delete' ); return false;">
	</span>
</div>

<div id="comments" class="container manage">

<?php $theme->display('comments_items'); ?>

</div>


<div class="container transparent item comments controls">
	<span class="checkboxandselected pct25">
		<input type="checkbox">
		<span class="selectedtext minor none"><?php _e('None selected'); ?></span>
	</span>
	<span class="buttons">
		<input type="submit" name="do_approve" value="<?php _e('Approve'); ?>" class="approve button" onclick="itemManage.update( 'approve' ); return false;">
		<input type="submit" name="do_unapprove" value="<?php _e('Unapprove'); ?>" class="unapprove button" onclick="itemManage.update( 'unapprove' ); return false;">
		<input type="submit" name="do_spam" value="<?php _e('Spam'); ?>" class="spam button" onclick="itemManage.update( 'spam' ); return false;">
		<input type="submit" name="do_delete" value="<?php _e('Delete'); ?>" class="delete button" onclick="itemManage.update( 'delete' ); return false;">
	</span>
</div>

</form>

<script type="text/javascript">
liveSearch.search= function() {
	spinner.start();

	$.post(
		'<?php echo URL::get('admin_ajax', array('context' => 'comments')) ?>',
		'&search=' + liveSearch.input.val() + '&limit=20',
		function(json) {
			$('#comments').html(json.items);
			// we hide and show the timeline to fix a firefox display bug
			$('.years').html(json.timeline).hide();
			spinner.stop();
			itemManage.initItems();
			$('.years').show();
			timeline.reset();
			findChildren();
		},
		'json'
		);
};

timelineHandle.loupeUpdate = function(a,b,c) {
	spinner.start();

	var search_args= $('.search input').val();

	$.ajax({
		type: 'POST',
		url: "<?php echo URL::get('admin_ajax', array('context' => 'comments')); ?>",
		data: 'offset=' + (parseInt(c) - parseInt(b)) + '&limit=' + (1 + parseInt(b) - parseInt(a)) + '&search=' + search_args,
		dataType: 'json',
		success: function(json){
			$('#comments').html(json.items);
			spinner.stop();
			itemManage.initItems();
			inEdit.init();
			inEdit.deactivate();
			findChildren();
		}
	});
};
</script>


<?php include('footer.php'); ?>
