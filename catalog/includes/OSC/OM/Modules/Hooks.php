<?php
/**
  * osCommerce Online Merchant
  *
  * @copyright Copyright (c) 2015 osCommerce; http://www.oscommerce.com
  * @license GPL; http://www.oscommerce.com/gpllicense.txt
  */

namespace OSC\OM\Modules;

use OSC\OM\Apps;

class Hooks extends \OSC\OM\ModulesAbstract
{
    public function getInfo($app, $key, $data)
    {
        $result = [];

        foreach ($data as $code => $class) {
            $class = $this->ns . $app . '\\' . $class;

            if (is_subclass_of($class, 'OSC\OM\Modules\\' . $this->code . 'Interface')) {
                $result[$app . '\\' . $key . '\\' . $code] = $class;
            }
        }

        return $result;
    }

    public function getClass($module)
    {
        list($app, $group, $code) = explode('\\', $module, 3);

        $info = Apps::getInfo($app);

        if (isset($info['modules'][$this->code][$group][$code])) {
            return $this->ns . $app . '\\' . $info['modules'][$this->code][$group][$code];
        }
    }

    public function filter($modules, $filter)
    {
        $result = [];

        foreach ($modules as $key => $data) {
            if (($key == $filter['site'] . '/' . $filter['group']) && isset($data[$filter['hook']])) {
                $result[$key] = $data;
            }
        }

        return $result;
    }
}
