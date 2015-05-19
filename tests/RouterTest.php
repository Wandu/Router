<?php
namespace Jicjjang\June;

use Jicjjang\June\stubs\AdminController;
use Psr\Http\Message\ServerRequestInterface;
use Mockery;
use PHPUnit_Framework_TestCase;
use ArrayObject;

class RouterTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    public function testMethodsWithMap()
    {
        $router = new Router;

        $this->assertEquals(0, $router->count());
        $this->assertEquals(0, count($router));

        $handler = function () {
            return "!!!";
        };

        $router->get('/', $handler);
        $router->post('/', $handler);
        $router->put('/', $handler);
        $router->delete('/', $handler);
        $router->options('/', $handler);

        $this->assertEquals(5, $router->count());
        $this->assertEquals(5, count($router));
    }

    public function testDispatch()
    {
        $router = new Router;

        $getMock = Mockery::mock(ServerRequestInterface::class);
        $getMock->shouldReceive('getMethod')->andReturn('GET');
        $getMock->shouldReceive('getUri->getPath')->andReturn('/');
        $getMock->shouldReceive('setArguments')->with([
        ]);

        $postMock = Mockery::mock(ServerRequestInterface::class);
        $postMock->shouldReceive('getMethod')->andReturn('POST');
        $postMock->shouldReceive('getUri->getPath')->andReturn('/');
        $postMock->shouldReceive('setArguments')->with([
        ]);

        $getCalled = 0;
        $postCalled = 0;
        $router->get(
            '/',
            function (ServerRequestInterface $req, \Closure $next) {
                return $next($req) . ' getMiddleware';
            },
            function (ServerRequestInterface $req) use (&$getCalled) {
                $getCalled++;
                return 'get';
            }
        );

        $router->post(
            '/',
            function (ServerRequestInterface $req, \Closure $next) {
                return $next($req) . ' postMiddleware';
            },
            function (ServerRequestInterface $req) use (&$postCalled) {
                $postCalled++;
                return 'post';
            }
        );

        $this->assertEquals('get getMiddleware', $router->dispatch($getMock));
        $this->assertEquals(1, $getCalled);
        $this->assertEquals(0, $postCalled);

        $this->assertEquals('post postMiddleware', $router->dispatch($postMock));
        $this->assertEquals(1, $getCalled);
        $this->assertEquals(1, $postCalled);
    }

    public function testDispatchWithArguments()
    {
        $router = new Router;

        $getMock = Mockery::mock(ServerRequestInterface::class);
        $getMock->shouldReceive('getMethod')->andReturn('GET');
        $getMock->shouldReceive('getUri->getPath')->andReturn('/jicjjang/hello');
        $getMock->shouldReceive('withAttribute')->andReturn($getMock);

        $router->get(
            '/{name}/{message}',
            function (ServerRequestInterface $req, \Closure $next) {
                return $next($req) . ' getMiddleware';
            },
            function (ServerRequestInterface $req) {
                return 'get';
            }
        );

        $this->assertEquals('get getMiddleware', $router->dispatch($getMock));
    }

    public function testBindingController()
    {
        $controllerMock = Mockery::mock(ControllerInterface::class);

        $containerMock = Mockery::mock(ArrayObject::class);
        $containerMock->shouldReceive('offsetSet')->with('admin', $controllerMock);
        $containerMock->shouldReceive('offsetGet')->with('admin')->andReturn($controllerMock);

        $router = new Router;

        $this->assertSame($router, $router->setController('admin', $controllerMock));
        $this->assertSame($controllerMock, $router->getController('admin'));

        $router = new Router($containerMock);

        $this->assertSame($router, $router->setController('admin', $controllerMock));
        $this->assertSame($controllerMock, $router->getController('admin'));
    }

    public function testAnyMethod()
    {
        $router = new Router;

        $anyMock = Mockery::mock(ServerRequestInterface::class);
        $anyMock->shouldReceive('getMethod')->andReturn('GET');
        $anyMock->shouldReceive('getUri->getPath')->andReturn('/');
        $anyMock->shouldReceive('setArguments')->with([
        ]);

        $router->any('/', function () {
            return 'any';
        });

        $this->assertEquals('any', $router->dispatch($anyMock));
        $this->assertEquals('any', $router->dispatch($anyMock));
    }

    public function testExecuteWithController()
    {
        $router = new Router();

        $router->setController('admin', new AdminController());

        $getMock = Mockery::mock(ServerRequestInterface::class);
        $getMock->shouldReceive('getMethod')->andReturn('GET');
        $getMock->shouldReceive('getUri->getPath')->andReturn('/');
        $getMock->shouldReceive('setArguments')->with([
        ]);

        $router->get('/', "middleware@admin", "action@admin");

        $this->assertEquals('Hello World!!!', $router->dispatch($getMock));
    }

    public function testGroup()
    {
        $mockRequest = Mockery::mock(ServerRequestInterface::class);
        $mockRequest->shouldReceive('getMethod')->andReturn('GET');
        $mockRequest->shouldReceive('getUri->getPath')->andReturn('/hello/world');

        $router = new Router();

        $router->group('/hello', function () use ($router) {
            $router->get('/world', function () { return 'world!'; });
            $router->get('/another', function () { return 'another!!'; });
        });

        $this->assertEquals(2, count($router));

        $this->assertEquals('world!', $router->dispatch($mockRequest));
    }
}
