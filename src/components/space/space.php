<?

class Component_space extends Component {
  function init() {
    $this->orm = OrmManager::singleton();
    $this->orm->LoadModel("space");

    $cfg  = ConfigManager::singleton();
    $cfg->current["page"]["frame"] = "space";
  }

  function controller_space($args) {
    $vars["content"] = any($args["content"], array("component" => "space.world"));
/*
    $vars["view"] = any($args["view"], "thingtypes");
    if (!empty($args["thinglink"])) {
      $add = ComponentManager::fetch("space.thinglink", array("create" => $args["thinglink"]), "data");
      $vars["errors"]["thinglink"] = $add["error"];
      $vars["view"] = "thinglinks";
    }
*/
    return '';
  }
  function controller_thingtypes($args) {
    $datatype = any($args["datatype"], "tree");
    if (!empty($args["thingtype"])) {
      $add = ComponentManager::fetch("space.thingtype", array("create" => $args["thingtype"]), "data");
      $vars["errors"]["thingtype"] = $add["error"];
      $vars["view"] = "thingtypes";
    }
    $thingtypes = $this->orm->select("ThingType", "ORDER BY type,property");
    if ($datatype == "tree") {
      foreach ($thingtypes as $k) {
        if (!isset($vars["thingtypes"][$k->type]->type)) {
          $vars["thingtypes"][$k->type]->type = $k->type;
        }
        $vars["thingtypes"][$k->type]->properties[$k->property][$k->propertykey] = $k->value;
      }
    } else {
      $vars["thingtypes"] = $thingtypes;
    }
    $vars["errors"] = $args["errors"];
    return $this->GetComponentResponse("./thingtypes.tpl", $vars);
  }
  function controller_thingtype($args) {
    $vars["item"] = $args["item"];
    if (!empty($args["create"])) {
      $newitem = new ThingType();
      foreach ($args["create"] as $k=>$v) {
        $newitem->{$k} = $v;
      }
      $vars["item"] = $newitem;
      try {
        $this->orm->save($newitem);
        $vars["error"] = false;
      } catch (Exception $e) {
        $vars["error"] = true;
      }
    }
    return $this->GetComponentResponse("./thingtype.tpl", $vars);
  }
  function controller_thingtype_create($args) {
    $vars["thingtype"] = $args["thingtype"];
    $vars["error"] = $args["error"];
    return $this->GetComponentResponse("./thingtype_create.tpl", $vars);
  }
  function controller_things($args) {
    $datatype = any($args["datatype"], "tree");
    $where = "";
    $vars["name"] = "universe";
    $vars["parentname"] = "";
    $vars["type"] = "universe";
    $vars["quantity"] = "1";
    $vars["attributes"] = array("capacity" => array("sector" => 1000));
    
    if (!empty($args["thing"])) {
      $add = ComponentManager::fetch("space.thing", array("create" => $args["thing"]), "data");
      $vars["errors"]["thing"] = $add["error"];
      $vars["view"] = "things";
    }
    if (!empty($args["from"])) {
      $from = $args["from"] . (substr($args["from"], -1) != '/' ? '/' : '');
      $where .= "CONCAT({Thing.parentname},'/',{Thing.name}) LIKE '$from%'";

      $fromparts = explode("/", $from);
      $fromparentname = implode("/", array_slice($fromparts, 0, count($fromparts)-2));
      $fromname = $fromparts[count($fromparts)-2];

      $fromthing = ORMManager::from("Thing")->with("ThingType", "ThingProperty")->where("{Thing.parentname}=? AND {Thing.name}=?", array($fromparentname, $fromname))->find();
      if (!empty($fromthing[0])) {
        $this->mergeProperties($fromthing[0], $fromthing[0]->getThingTypes());
        $this->mergeProperties($fromthing[0], $fromthing[0]->getThingProperties());
        $vars = object_to_array($fromthing[0]);
      }
    }
    //$where .= "ORDER BY CONCAT(parentname,'/',name)";
    $things = ORMManager::from("Thing")->with("ThingType", "ThingProperty")->where($where)->orderBy("CONCAT({Thing.parentname},'/',{Thing.name})")->find();
    foreach ($things as $thing) {
      // FIXME - this is currently triggering many queries because the Outlet one-to-many processing needs some work
      $this->mergeProperties($thing, $thing->getThingTypes());
      $this->mergeProperties($thing, $thing->getThingProperties());
      if ($datatype == "tree") {
        $objkey = $thing->parentname . "/" . $thing->name;
        if (!empty($from)) {
          $objkey = preg_replace("|^" . $from . "|", "/", $objkey);
        }
        object_set($vars, str_replace("/", "/things/", $objkey), $thing, "/");
      } else {
        $vars["things"][] = $thing;
      }
    }
    return $this->GetComponentResponse("./things.tpl", $vars);
  }
  function controller_thing($args) {
    if (!empty($args["item"])) {
      $vars["item"] = $args["item"];
    } else if (!empty($args["parentname"]) && !empty($args["name"])) {
      $vars["item"] = ORMManager::load("Thing", array($args["parentname"], $args["name"]));
      $things = ComponentManager::fetch("space.things", array("from" => $args["parentname"] . "/" . $args["name"]), "data");
      $vars["item"]->things = $things["things"];
    }
    if (!empty($args["create"])) {
      $newitem = new Thing();
      foreach ($args["create"] as $k=>$v) {
        $newitem->{$k} = $v;
      }
      $vars["item"] = $newitem;
      try {
        $this->orm->save($newitem);
        $vars["error"] = false;
      } catch (Exception $e) {
        $vars["error"] = true;
      }
    }
    $vars["show"] = array("properties" => any($args["showproperties"], false), 
                          "things" => any($args["showproperties"], true),
                          "links" => any($args["showlinks"], false));
    return $this->GetComponentResponse("./thing.tpl", $vars);
  }
  function controller_thing_edit($args) {
    $didupdate = false;
    if (!empty($args["addproperty"])) {
      $newitem = new ThingProperty();
      $newitem->parentname = $args["parentname"];
      $newitem->name = $args["name"];
      foreach ($args["addproperty"] as $k=>$v) {
        $newitem->{$k} = $v;
      }
      //$vars["item"] = $newitem;
      try {
        $this->orm->save($newitem);
        $vars["error"] = false;
        $didupdate = true;
      } catch (Exception $e) {
        $vars["error"] = true;
      }
    }
    if (!empty($args["thing"])) {
      $vars["thing"] = $args["item"];
    } else if (!empty($args["name"])) {
      $vars["thing"] = ORMManager::load("Thing", array($args["parentname"], $args["name"]));
      $types = $vars["thing"]->getThingTypes()->toArray();
      $properties = $vars["thing"]->getThingProperties()->toArray();
      $updates = any($args["updateproperty"], array()); 
      $updatetypes = any($args["updatetype"], array()); 
      foreach ($properties as $prop) {
        if (isset($updates[$prop->property]) && isset($updates[$prop->property][$prop->propertykey])) {
          if ($prop->value != $updates[$prop->property][$prop->propertykey] || $prop->propertytype != $updatetypes[$prop->property][$prop->propertykey]) {
            $prop->value = $updates[$prop->property][$prop->propertykey];
            $prop->propertytype = $updatetypes[$prop->property][$prop->propertykey];
            OrmManager::save($prop);
            $didupdate = true;
          }
        }
        $vars["properties"][$prop->property][$prop->propertykey] = $prop;
      }
      if ($didupdate) {
        header("Location: " . $this->root->request["url"]);
      }
      $things = ComponentManager::fetch("space.things", array("from" => $args["parentname"] . "/" . $args["name"]), "data");
      $vars["thing"]->things = $things["things"];
    }

    return $this->GetComponentResponse("./thing_edit.tpl", $vars);
  }
  function controller_thing_create($args) {
    $vars["thing"] = $args["thing"];
    $vars["error"] = $args["error"];
    if (!empty($args["parentthing"])) {
      $vars["item"]->parentname = $args["parentthing"]->parentname . "/" . $args["parentthing"]->name;;
    }
    return $this->GetComponentResponse("./thing_create.tpl", $vars);
  }
  function controller_thinglinks($args) {
    $vars["thinglinks"] = $this->orm->select("ThingLink");
    return $this->GetComponentResponse("./thinglinks.tpl", $vars);
  }
  function controller_thinglink($args) {
    $vars["item"] = $args["item"];
    if (!empty($args["create"])) {
      $newitem = new ThingLink();
      foreach ($args["create"] as $k=>$v) {
        $newitem->{$k} = $v;
      }
      $vars["item"] = $newitem;
      try {
        $this->orm->save($newitem);
        $vars["error"] = false;
      } catch (Exception $e) {
        $vars["error"] = true;
      }
    }
    return $this->GetComponentResponse("./thinglink.tpl", $vars);
  }
  function controller_thinglink_create($args) {
    $vars["thinglink"] = $args["thinglink"];
    $vars["error"] = $args["error"];
    return $this->GetComponentResponse("./thinglink_create.tpl", $vars);
  }
  function controller_world($args) {
    $data = ComponentManager::fetch("space.thingtypes", array("datatype" => "tree"), "data");
    $vars["anchor"] = any($args["anchor"], "/milky way/solar system/sol/earth");
    $vars["skybox"] = !isset($args["skybox"]) || !empty($args["skybox"]);
    $vars["capacities"] = $data["thingtypes"];
    $data = ComponentManager::fetch("space.things", array("datatype" => "tree"), "data");
    $vars["things"] = $data["things"];
    $vars["highres"] = any($args["hq"], false);

    return $this->GetComponentResponse("./world.tpl", $vars);
  }
/*
  function controller_test($args) {
    $datatype = any($args["datatype"], "tree");
    $things = ORMManager::from("Thing")->with("ThingType", "ThingProperty")->find();
    foreach ($things as $thing) {
      $allprops = array($thing->getThingTypes(), $thing->getThingProperties());
      foreach ($allprops as $props) {
        if (!empty($props)) {
          foreach ($props as $prop) {
            $thing->properties[$prop->property][$prop->propertykey] = $this->castProperty($prop);
          }
        }
      }
      if ($datatype == "tree") {
        object_set($vars, str_replace("/", "/things/", $thing->parentname . "/" . $thing->name), $thing, "/");
      } else {
        $vars["things"][] = $thing;
      }
    }

    return $this->GetComponentResponse(NULL, $vars);
  }
*/
  function controller_viewport($args) {
    $vars["anchor"] = any($args["anchor"], "/");
    $data = ComponentManager::fetch("space.things", array("from" => $vars["anchor"], "datatype" => "tree"), "data");
    $vars = $data;
    $vars["skybox"] = !empty($args["skybox"]);
    $vars["highres"] = any($args["highres"], false);
    return $this->GetComponentResponse("./viewport.tpl", $vars);
  }
  function controller_fly($args) {
    $vars["root"] = any($args["root"], "/milky way/solar system/sol/earth/north america/stupidtown");
    header("Access-Control-Allow-Origin: http://cdn.supcrit.com");
    $vars["sector"] = ComponentManager::fetch("space.things", array("from" => $vars["root"]), "data");
    $vars["types"] = $this->getUniqueThingTypes($vars["sector"]);
    $vars["craters1"] = file('http://www.meobets.com/~lazarus/craters125km.mkr',  FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $vars["craters2"] = file('http://www.meobets.com/~lazarus/craters75-125km.mkr',  FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $vars["craters3"] = file('http://www.meobets.com/~lazarus/craters25-75km.mkr',  FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $vars["craters4"] = file('http://www.meobets.com/~lazarus/craters0-25km.mkr',  FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return $this->GetComponentResponse("./fly.tpl", $vars);
  }
  function controller_fusion($args) {
    $vars["root"] = any($args["root"], "/milky way/solar system/sol/earth/north america/stupidtown");
    header("Access-Control-Allow-Origin: http://cdn.supcrit.com");
    $vars["sector"] = ComponentManager::fetch("space.things", array("from" => $vars["root"]), "data");
    $vars["types"] = $this->getUniqueThingTypes($vars["sector"]);
    return $this->GetComponentResponse("./fusion.tpl", $vars);
  }
  function controller_enginetest($args) {
    $cfg->current["page"]["frame"] = "html.frame";
    $vars["root"] = any($args["root"], "/milky way/solar system/sol/earth/north america/stupidtown");
    header("Access-Control-Allow-Origin: http://cdn.supcrit.com");
    $vars["sector"] = ComponentManager::fetch("space.things", array("from" => $vars["root"]), "data");
    $vars["types"] = $this->getUniqueThingTypes($vars["sector"]);
    return $this->GetComponentResponse("./enginetest.tpl", $vars);
  }
  function controller_imperium($args) {
    $root = any($args["root"], "/Starbinger");
    header("Access-Control-Allow-Origin: http://cdn.supcrit.com");
    $vars["sector"] = ComponentManager::fetch("space.things", array("from" => $root), "data");
    $vars["types"] = $this->getUniqueThingTypes($vars["sector"]);
    
    $stars = array(
     "M"=>array('color'=>'0xbb0000','temp'=>'2600,4300','radius'=>'.15,.72','mass'=>'.06,.67','luminosity'=>'.001,.15','hzone'=>'.03,.39','lifetime'=>'700000,45000'),
     "K"=>array('color'=>'0xffd575','temp'=>'4300,5700','radius'=>'.72,.92','mass'=>'.67,.92','luminosity'=>'.15,.79','hzone'=>'.39,.89','lifetime'=>'45000,12000'),
     "G"=>array('color'=>'0xfff7c0','temp'=>'5700,6400','radius'=>'.92,1.3','mass'=>'.92,1.3','luminosity'=>'.79,3.2','hzone'=>'.89,1.8','lifetime'=>'12000,3200'),
     "F"=>array('color'=>'0xffffa0','temp'=>'6400,8200','radius'=>'1.3,1.7','mass'=>'1.3,2.0','luminosity'=>'3.2,14','hzone'=>'1.8,3.7','lifetime'=>'3200,1000'),
     "A"=>array('color'=>'0xeeeeff','temp'=>'8200,12000','radius'=>'1.7,3.0','mass'=>'2.0,3.8','luminosity'=>'14,180','hzone'=>'3.7,13','lifetime'=>'1000,150'),
     "B"=>array('color'=>'0xafeeee','temp'=>'12000,35000','radius'=>'3.0,7','mass'=>'3.8,23','luminosity'=>'180,170000','hzone'=>'13,410','lifetime'=>'150,6'),
     "O"=>array('color'=>'0xa7eeff','temp'=>'35000,55000','radius'=>'7,12','mass'=>'23,140','luminosity'=>'170000,1600000','hzone'=>'410,1400','lifetime'=>'6,1.5')
    );
    
    $order = array(
      '30'=>'K',
      '50'=>'G',
      '65'=>'F',
      '80'=>'A',
      '90'=>'B',
      '95'=>'O'
    );
    
    $seed = any($args["seed"],rand());
    srand($seed);
    $vars["random"] = $rand = 60;//rand(0,100);
    $vars["type"] = 'M';
    $star = $stars['M'];
    
    foreach ($order as $p=>$type) {
      if ($rand >= $p) {
        $vars["type"] = $type;
        $star = $stars[$type];
      }
    }
    
    $vars["random2"] = $rand2 = rand(0,100) * .01;
    
    $temp = explode(',',$star['temp']);
    $radius = explode(',',$star['radius']);
    $mass = explode(',',$star['mass']);
    $luminosity = explode(',',$star['luminosity']);
    $hzone = explode(',',$star['hzone']);
    $lifetime = explode(',',$star['lifetime']);
    
    $s = array(
      'type'=> $vars["type"] . floor($rand2 * 10),
      'seed'=> $seed,
      'color'=> $star["color"],
      'temp'=> ($temp[0] + (($temp[1] - $temp[0]) * $rand2)),
      'radius'=> (($radius[0] + (($radius[1] - $radius[0]) * $rand2)) * 13920000),
      'mass'=> ($mass[0] + (($mass[1] - $mass[0]) * $rand2)),
      'lum'=> ($luminosity[0] + (($luminosity[1] - $luminosity[0]) * $rand2)),
      'hzone'=> ($hzone[0] + (($hzone[1] - $hzone[0]) * $rand2)),
      'lifetime'=> ($lifetime[0] + (($lifetime[1] - $lifetime[0]) * $rand2)),
      'randoms'=> $rand . ', ' . $rand2
    );
    
    {
      parentname: '/Starbinger/Sector 002',
      name: 'Star',
      type: 'star',
      quantity: 1,
      properties: {
        'generated': {
          'type': 'G4',
          'seed': '0.434830',
          'color': '0xfff7c0',
          'temp': '6000',
          'radius': '1.0',
          'mass': '1.0',
          'lum': '1.2',
          'hzone': '1.1',
          'lifetime': '15000',
          'randoms': '60, 0.434830'
        },
        'physical': {
          "position": [ 0, 0, -11570000 ],
          "radius": '1.0'
        },
        'render': {
          "color": '0xfff7c0'
        }
      }
    }
    
    $vars["sector"]["things"]["Star"] = array(
      name=>'Star',
      type=>'star',
      parentname=>'/Starbinger/Sector 001',
      quantity=>1,
      properties=>array(
        'generated'=>$s,
        'physical'=>array("position"=>array(0,0,-11570000),"radius"=>$s['radius']),
        'render'=>array("color"=>$s['color'])
      )
    );
    
    return $this->GetComponentResponse("./starbinger.tpl", $vars);
  }

  function getUniqueThingTypes($thing) {
    $types = array();
    if (is_array($thing)) {
      $types[$thing["type"]] = 1;
      $subthings = $thing["things"];
    } else {
      $types[$thing->type] = 1;
      $subthings = $thing->things;
    }
    if (!empty($subthings)) {
      foreach ($subthings as $subthing) {
        $subtypes = $this->getUniqueThingTypes($subthing);
        foreach ($subtypes as $subkey=>$subval) {
          $types[$subkey] += $subval;
        }
      }
    }
    return $types;
  }
  function castProperty($prop) {
    switch($prop->propertytype) {
      case 'float':
        $val = (float) $prop->value;
        break;
      case 'int':
      case 'integer':
        $val = (int) $prop->value;
        break;
      case 'vector':
        $val = explode(",", $prop->value);
        for ($i = 0; $i < count($val); $i++) {
          $val[$i] = (float) $val[$i];
        }
        break;
      case 'json':
        $val = json_decode($prop->value);
        break;
      default:
        $val = $prop->value;
    }
    return $val;
  }
  function mergeProperties($thing, $props) {
    if (!empty($props)) {
      foreach ($props as $prop) {
        switch ($prop->propertykey) {
          case 'normalmap':
          case 'texture':
          //case 'heightmap':
            $value = DependencyManager::$locations['imageswww'] . $this->castProperty($prop);
            break;
          default:
            $value = $this->castProperty($prop);
        }
        $thing->properties[$prop->property][$prop->propertykey] = $value;
      }
    }
  }
}  
