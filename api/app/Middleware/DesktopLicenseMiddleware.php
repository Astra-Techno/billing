<?php
namespace App\Middleware;
use App\Core\DesktopLicenseLocal;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
final class DesktopLicenseMiddleware { public function __invoke(Request $request,Handler $handler): Response { if(($_ENV['DESKTOP_MODE']??'')==='true'){ $path=$request->getUri()->getPath();$allowed=str_contains($path,'/task/DesktopLicense/')||str_contains($path,'/task/Desktop/')||str_ends_with($path,'/logout');if(!$allowed)DesktopLicenseLocal::requireActive(); }return $handler->handle($request); } }
