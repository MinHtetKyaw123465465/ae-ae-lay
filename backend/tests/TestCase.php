<?php
namespace Tests;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Application;
abstract class TestCase extends BaseTestCase {
 public function createApplication():Application {
  $app=parent::createApplication();$driver=$app['config']->get('database.default');$database=$app['config']->get('database.connections.'.$driver.'.database');
  // This executes before RefreshDatabase can run migrations.
  if(!(($driver==='sqlite'&&$database===':memory:')||(is_string($database)&&str_ends_with($database,'_testing'))))throw new \RuntimeException('Tests must use SQLite :memory: or a dedicated database ending in _testing.');
  return $app;
 }
}
