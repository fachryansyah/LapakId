<?php

namespace Fahri\LapakId\Controllers;

use Fahri\LapakId\Core\Controller;
use Fahri\LapakId\Core\Env;
use Throwable;

class HelloController extends Controller
{
    public function index(): string
    {
        $databaseStatus = 'MySQL connection has not been checked yet.';
        $databaseInfo = null;
        $databaseError = null;

        try {
            $databaseInfo = $this->db()->selectOne(
                'SELECT DATABASE() AS database_name, NOW() AS server_time, :app_name AS app_name',
                ['app_name' => Env::get('APP_NAME', 'LapakId')]
            );

            $databaseStatus = 'Connected to MySQL using PDO prepared statements.';
        } catch (Throwable $throwable) {
            $databaseStatus = 'Failed to connect to MySQL.';
            $databaseError = $throwable->getMessage();
        }

        return $this->render('hello-world.twig', [
            'title' => 'Hello World',
            'message' => 'MVC PHP project with Phroute, Twig, and MySQL is running.',
            'appPort' => Env::get('APP_PORT', '8000'),
            'databaseStatus' => $databaseStatus,
            'databaseInfo' => $databaseInfo,
            'databaseError' => $databaseError,
        ]);
    }
}
