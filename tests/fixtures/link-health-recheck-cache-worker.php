<?php
function release_invoke( $target, $method, ...$args ) {
	$reflection = new ReflectionMethod( $target, $method );
	if ( PHP_VERSION_ID < 80100 ) { $reflection->setAccessible( true ); }
	return $reflection->invoke( is_object( $target ) ? $target : null, ...$args );
}

$GLOBALS['repro_db_meta']=[]; $GLOBALS['repro_meta_cache']=[];
function get_post_meta($id,$key='',$single=false){
 if(!array_key_exists($id,$GLOBALS['repro_meta_cache'])) $GLOBALS['repro_meta_cache'][$id]=$GLOBALS['repro_db_meta'][$id]??[];
 return $GLOBALS['repro_meta_cache'][$id][$key]??'';
}
function update_post_meta($id,$key,$value){$GLOBALS['repro_db_meta'][$id][$key]=$value;unset($GLOBALS['repro_meta_cache'][$id]);return true;}
function wp_cache_delete($id,$group=''){if($group==='post_meta')unset($GLOBALS['repro_meta_cache'][$id]);return true;}
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/bootstrap.php';
require dirname( __DIR__ ) . '/UtilityModulesTest.php';
use Functionalities\Features\Link_Health as Links;
use Functionalities\Storage\Data_Directory as Directory;
use Functionalities\Storage\Atomic_JSON_Store as Store;
$t=new UtilityModulesTest('test_batch_resumes_within_one_post_and_then_completes');
release_invoke($t,'setUp');
try {
 $post=release_invoke($t,'post',10,'<a href="https://example.test/a">A</a><a href="https://example.test/b">B</a>');
 $GLOBALS['functionalities_http_handler']=static function(){return ['response'=>['code'=>404]];};
 Links::start_scan(); Links::run_batch();
 $GLOBALS['functionalities_http_handler']=static function(){
  Store::update(Directory::file('link-health-state.json'),static function($state){
   $GLOBALS['repro_db_meta'][10][Links::META_KEY]['rows'][md5('https://example.test/a')]['status']='ok';
   $GLOBALS['repro_db_meta'][10][Links::META_KEY]['rows'][md5('https://example.test/a')]['code']=200;
   return $state;
  });
  return ['response'=>['code'=>200]];
 };
 $result=Links::recheck(10,'https://example.test/b');
 echo json_encode(['result'=>$result,'after_rows'=>get_post_meta(10,Links::META_KEY,true)['rows']],JSON_PRETTY_PRINT),"\n";
} finally {release_invoke($t,'tearDown');}
