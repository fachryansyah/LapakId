<?php

declare(strict_types=1);

namespace Fahri\LapakId\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

class View
{
    private Environment $twig;

    public function __construct()
    {
        $viewsPath = dirname(__DIR__, 2) . '/views';
        $loader = new FilesystemLoader($viewsPath);

        $this->twig = new Environment($loader, [
            'cache' => false,
            'debug' => true,
        ]);

        $this->twig->addFilter(new TwigFilter('json_decode', function ($string) {
            return json_decode((string)$string, true);
        }));
    }

    public function render(string $template, array $data = []): string
    {
        return $this->twig->render($template, array_merge([
            'appName' => Env::get('APP_NAME', 'LapakId'),
            'currentUser' => Auth::user(),
            'flash' => Flash::pullAll(),
            'currentPath' => parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
        ], $data));
    }
}
