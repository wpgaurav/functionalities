<?php
/**
 * Execute the generated service worker against a browser API harness.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

final class PwaServiceWorkerTest extends TestCase {
	/**
	 * Capture the worker endpoint in an isolated process because it exits.
	 *
	 * @return string
	 */
	private function worker(): string {
		$root   = dirname( __DIR__ );
		$script = '<?php require ' . var_export( $root . '/tests/bootstrap.php', true ) . ';'
			. 'function status_header($status) {} function nocache_headers() {}'
			. 'require ' . var_export( $root . '/includes/features/class-pwa.php', true ) . ';'
			. '$GLOBALS["functionalities_test_options"]["functionalities_pwa"] = array("app_name"=>"Test", "short_name"=>"Test", "description"=>"Test", "cache_version"=>"v1", "precache_urls"=>"https://example.test/public\nhttps://example.test/member\nhttps://example.test/wp-admin/\nhttps://other.example/resource\nhttps://example.test/broken");'
			. '$method = new ReflectionMethod(\\Functionalities\\Features\\PWA::class, "output_service_worker"); if (PHP_VERSION_ID < 80100) { $method->setAccessible(true); } $method->invoke(null);';
		return $this->run_process( array( PHP_BINARY ), $script );
	}

	/**
	 * Run executable fixtures without touching the site or writing files.
	 *
	 * @param array  $command Command and arguments.
	 * @param string $script  Script supplied on stdin.
	 * @return string
	 */
	private function run_process( array $command, string $script ): string {
		$pipes = array();
		$child = proc_open( $command, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
		$this->assertIsResource( $child );
		fwrite( $pipes[0], $script );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error  = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$this->assertSame( 0, proc_close( $child ), $error );
		return $output;
	}

	/**
	 * Installation respects response privacy; activation preserves others' caches.
	 *
	 * @return void
	 */
	public function test_installation_privacy_and_cache_ownership(): void {
		$worker  = $this->worker();
		$harness = <<<'JS'
const vm = require("node:vm");
const assert = require("node:assert/strict");
const events = {};
const stores = new Map([
  ["another-plugin-assets", new Map()],
  ["func-pwa-runtime-old", new Map()],
  ["func-pwa-images-v10", new Map()]
]);
const fetched = [];
class BrowserRequest extends Request {
  constructor(input, options) {
    super(typeof input === "string" ? new URL(input, "https://example.test/").href : input, options);
  }
}
const fetchResponse = async req => {
  fetched.push({url:req.url, credentials:req.credentials});
  const path = new URL(req.url).pathname;
  if(path === "/broken") throw new Error("offline");
  const privateResponse = path === "/member" || path === "/wp-admin/" || (path === "/" && req.credentials !== "omit");
  return new Response(privateResponse ? "Private user information" : "Public page", {
    headers:{"Cache-Control":privateResponse ? "private, no-store" : "public, max-age=0"}
  });
};
const caches = {
  async open(name) {
    if(!stores.has(name)) stores.set(name,new Map());
    const store = stores.get(name);
    return {
      async add(req) { store.set(req.url,await fetchResponse(req)); },
      async put(req,res) { store.set(req.url,res); },
      async keys() { return [...store.keys()].map(url=>new BrowserRequest(url)); },
      async match(req) { return store.get(typeof req === "string" ? req : req.url); },
      async delete(req) { return store.delete(typeof req === "string" ? req : req.url); }
    };
  },
  async keys() { return [...stores.keys()]; },
  async delete(name) { return stores.delete(name); }
};
const context = vm.createContext({
  self:{location:{origin:"https://example.test",href:"https://example.test/functionalities-sw.js"},
    addEventListener(name,callback){events[name]=callback;},
    async skipWaiting(){}, clients:{async claim(){}}, registration:{}},
  caches, fetch:fetchResponse, Request:BrowserRequest, Response, URL
});
vm.runInContext(WORKER_SOURCE,context);
async function dispatch(name) {
  let pending;
  events[name]({waitUntil(work){pending=work;}});
  await pending;
}
(async()=>{
  await dispatch("install");
  const core = stores.get(vm.runInContext("CORE_CACHE",context));
  assert.equal(core.has("https://example.test/member"),false,"private responses must not be precached");
  assert.equal(core.has("https://example.test/wp-admin/"),false,"administration URLs must not be precached");
  assert.equal(core.has("https://other.example/resource"),false,"cross-origin URLs must not be precached");
  assert.equal(await core.get("https://example.test/").text(),"Public page","homepage precache must be anonymous");
  assert.equal(core.has("https://example.test/public"),true,"public precaching remains supported");
  assert.equal(core.has("https://example.test/functionalities-offline/"),true,"the public offline shell remains available");
  assert.ok(fetched.every(req=>req.credentials==="omit"),"precache must omit authentication cookies");
  await dispatch("activate");
  assert.equal(stores.has("another-plugin-assets"),true,"activation must preserve another plugin's cache");
  assert.equal(stores.has("func-pwa-runtime-old"),false,"obsolete owned caches must be removed");
  assert.equal(stores.has("func-pwa-images-v10"),false,"version substring matches must not retain obsolete caches");
  console.log("privacy and ownership verified");
})().catch(error=>{console.error(error);process.exitCode=1;});
JS;
		$harness = str_replace( 'WORKER_SOURCE', json_encode( $worker ), $harness );
		$this->assertStringContainsString( 'privacy and ownership verified', $this->run_process( array( 'node' ), $harness ) );
	}
}
