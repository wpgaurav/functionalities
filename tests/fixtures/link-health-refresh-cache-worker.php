<?php
function release_invoke( $target, $method, ...$args ) {
	$reflection = new ReflectionMethod( $target, $method );
	if ( PHP_VERSION_ID < 80100 ) { $reflection->setAccessible( true ); }
	return $reflection->invoke( is_object( $target ) ? $target : null, ...$args );
}

$GLOBALS['repro_post_cache']=[];
function get_post($id){if(is_object($id))return $id;if(!isset($GLOBALS['repro_post_cache'][$id])&&isset($GLOBALS['functionalities_test_posts'][$id]))$GLOBALS['repro_post_cache'][$id]=clone $GLOBALS['functionalities_test_posts'][$id];return $GLOBALS['repro_post_cache'][$id]??null;}
function wp_cache_delete($id,$group=''){if($group==='posts')unset($GLOBALS['repro_post_cache'][$id]);return true;}
function clean_post_cache($id){wp_cache_delete($id,'posts');}
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/bootstrap.php';
require dirname( __DIR__ ) . '/UtilityModulesTest.php';
use Functionalities\Features\Link_Health as Links;
use Functionalities\Features\Link_Health_Editor as Editor;
$t=new UtilityModulesTest('test_batch_resumes_within_one_post_and_then_completes');
release_invoke($t,'setUp');
try {
 $post=release_invoke($t,'post',10,'<a href="https://example.test/a">A</a><a href="https://example.test/b">B</a>');
 Links::start_scan(); Links::run_batch();
 $GLOBALS['functionalities_action_handler']=static function($hook){
  if($hook!=='save_post')return;
  $GLOBALS['functionalities_action_handler']=null;
  $cacheA=$GLOBALS['repro_post_cache'];
  $GLOBALS['repro_post_cache']=[];
  $GLOBALS['functionalities_test_user_id']=8;
  $preview=Editor::preview(10,'https://example.test/b','replace','https://example.test/b2');
  $apply=Editor::apply(10,$preview['token']);
  $GLOBALS['functionalities_test_user_id']=7;
  $GLOBALS['repro_post_cache']=$cacheA;
 };
 $preview=Editor::preview(10,'https://example.test/a','replace','https://example.test/a2');
 $apply=Editor::apply(10,$preview['token']);
 $saved=get_post_meta(10,Links::META_KEY,true);
 echo json_encode(['A_response'=>$apply,'DB_content'=>$GLOBALS['functionalities_test_posts'][10]->post_content,'report_urls'=>array_column($saved['rows'],'url'),'report_hash'=>$saved['hash'],'current_hash'=>Links::content_hash($GLOBALS['functionalities_test_posts'][10])],JSON_PRETTY_PRINT),"\n";
}finally{release_invoke($t,'tearDown');}
