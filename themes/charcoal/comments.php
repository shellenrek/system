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
<?php // Do not delete these lines
	if ( ! defined('HABARI_PATH' ) ) { die( _t('Please do not load this page directly. Thanks!') ); }
?>
		<?php if ( !$post->info->comments_disabled || $post->comments->moderated->count ) :?>
			<div id="post-comments">
			<?php if ( $post->comments->moderated->count ) : ?>
				<?php foreach ( $post->comments->moderated as $comment ) : ?>
				
				<div id="comment-<?php echo $comment->id; ?>" class="post-comment">
					
					<div class="post-comment-commentor">
						<h2>
							<a href="<?php echo $comment->url; ?>" rel="external"><?php echo $comment->name; ?></a>
						</h2>
					</div>
					<div class="post-comment-body">
						<?php echo $comment->content_out; ?>
						<p class="post-comment-link"><a href="#comment-<?php echo $comment->id; ?>" title="Time of this comment - Click for comment permalink"><?php $comment->date->out(); ?></a></p>
						<?php if ( $comment->status == Comment::STATUS_UNAPPROVED ) : ?>
						<p class="comment-message"><em><?php _e( 'Your comment is awaiting moderation' ) ;?></em></p>
						<?php endif; ?>
					</div>
				</div>
				<?php endforeach; ?>
			<?php else : ?>
				<h2><?php _e( 'Be the first to write a comment!' ); ?></h2>
			<?php endif; ?>
				<div id="post-comments-footer">
					<!-- TODO: A hook can be placed here-->
				</div>
			</div>
		<?php endif; ?>
