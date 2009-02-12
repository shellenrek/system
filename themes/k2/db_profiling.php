<?php /*

  Copyright 2007-2009 The Habari Project <http://www.habariproject.org/>

  Licensed under the Apache License, Version 2.0 (the "License");
  you may not use this file except in compliance with the License.
  You may obtain a copy of the License at

      http://www.apache.org/licenses/LICENSE-2.0

  Unless required by applicable law or agreed to in writing, software
  distributed under the License is distributed on an "AS IS" BASIS,
  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  See the License for the specific language governing permissions and
  limitations under the License.

*/ ?>
<style>
.prof_container {
  margin: 10 100;
  text-align: center;
  border: solid 1px #999;
  background-color: #f7f7f7;
  padding: 15;
}
.prof_header {
  font: 320% Arial, Helvetica;
  color: #000;
  text-align: left;
  margin: 0 0 6 0;
  padding: 0;
}
.prof_sql {
  font: 240% monospace, Courier;
  font-weight: bold;
  text-align: left;
  padding: 4 4 4 30;
  margin: 4 100;
  display: block;
}
.prof_time {
  font: 180%/2.0 Verdana, Tahoma, sans;
  color: red;
  text-align: left;
}
</style>
<div class="prof_container">
	<h1 class="prof_header"><?php _e('DB Profiling'); ?></h1>
	<?php
	$profiles = DB::get_profiles();
	$total_time_querying = 0;
	foreach ( $profiles as $profile ) {
	?>
	<div>
		<code class="prof_sql">
			<?php echo $profile->query_text; ?>
		</code>
		<div class="prof_time">
			<p><?php _e('Time to Execute:'); ?> <strong><?php echo $profile->total_time; ?></strong></p>
		</div>
		<?php if ( !empty( $profile->backtrace ) ) { ?>
		<pre style="text-align: left;">
			<strong>BACKTRACE:</strong><br/>
			<?php print_r($profile->backtrace); ?>
		</pre>
		<?php } ?>
	</div>
	<?php
			$total_time_querying+= $profile->total_time;
	}
	?>
	<div class="prof_time_total">
		<p><?php _e('Total Time Executing Queries:'); ?> <?php echo $total_time_querying; ?></p>
	</div>
</div>
