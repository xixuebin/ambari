<?php

/*
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class SelectNodes {
  private $logger;

  /**
   * Needs a default constructor else warnings will appear.
   */
  function __construct() {
    $this->logger = new HMCLogger("SelectNodes");
  }

  /** Helper function for creating an array for hostName and totalMem
   */
  function createHostMap($hostInfo) {
    $result = array("hostName" => $hostInfo["hostName"], "totalMem" => $hostInfo["totalMem"]);
    return $result;
  }
  /** Return only the enabled services.
   */
  function filterEnabledServices($services) {
    $enabledServices = array();
    foreach($services as $serviceName=>$serviceInfo) {
      if ($serviceInfo["isEnabled"] == 1) {
        $enabledServices[$serviceName] = $serviceInfo;
      }
    }
    return $enabledServices;
  }

  /**
   * Helper function to add HDFS NameNode
   */
  function addNameNode($serviceInfo, $result, $hostInfo) {
    if (array_key_exists("HDFS", $serviceInfo)) {

      $result["mastersToHosts"]["NAMENODE"] = $this->createHostMap($hostInfo);
    }
    $this->logger->log_info("Adding to result ".$result);
    return $result;
  }

  /**
   * Helper function to add SNameNode
   */
  function addSNameNode($serviceInfo, $result, $hostInfo) {
    if (array_key_exists("HDFS", $serviceInfo)) {
      $result["mastersToHosts"]["SNAMENODE"] = $this->createHostMap($hostInfo);
    }
    return $result;
  }

  /**
   * Helper function to add JobTracker.
   */
  function addJobTracker($serviceInfo, $result, $hostInfo) {
    if (array_key_exists("MAPREDUCE", $serviceInfo)) {
      $result["mastersToHosts"]["JOBTRACKER"] = $this->createHostMap($hostInfo);
    }
    return $result;
  }

  /**
   * Helper function to add HBase Master.
   */
  function addHBaseMaster($serviceInfo, $result, $hostInfo) {
    if (array_key_exists("HBASE", $serviceInfo)) {
      $result["mastersToHosts"]["HBASE_MASTER"] = $this->createHostMap($hostInfo);
    }
    return $result;
  }

  /**
   * Helper function to add Oozie server.
   */
  function addOozieServer($serviceInfo, $result, $hostInfo) {
    if (array_key_exists("OOZIE", $serviceInfo)) {
      $result["mastersToHosts"]["OOZIE_SERVER"] = $this->createHostMap($hostInfo);
    }
    return $result;
  }

  /**
   * Helper function to add Hive Server.
   */
  function addHiveServer($serviceInfo, $result, $hostInfo) {
    if (array_key_exists("HIVE", $serviceInfo)) {
      $result["mastersToHosts"]["HIVE_SERVER"] = $this->createHostMap($hostInfo);
    }
    return $result;
  }

  /**
   * Helper function to add Templeton.
   */
  function addTempletonServer($serviceInfo, $result, $hostInfo) {
    if (array_key_exists("TEMPLETON", $serviceInfo)) {
      $result["mastersToHosts"]["TEMPLETON_SERVER"] = $this->createHostMap($hostInfo);
    }
    return $result;
  }

  /**
   * Adds all the slaves to the hostlist given whats enabled
   */
  function addSlaves($db, $hostlist, $clusterName, $services, $gangliaMaster) {
    $db->addHostsToComponent($clusterName, "TASKTRACKER", $hostlist, "ASSIGNED", "");
    $db->addHostsToComponent($clusterName, "DATANODE", $hostlist, "ASSIGNED", "");
    if (array_key_exists("HBASE", $services)) {
      $db->addHostsToComponent($clusterName, "HBASE_REGIONSERVER", $hostlist, "ASSIGNED", "");
    }
    if (array_key_exists("GANGLIA", $services)) {
      $hosts = $this->getExcludeHosts($hostlist, array($gangliaMaster));
      if (sizeof($hosts) > 0) {
        $db->addHostsToComponent($clusterName, "GANGLIA_MONITOR", $hosts, "ASSIGNED", "");
      }
    }
  }

  /**
   * Return a list of hosts excluding the ones in
   * $excludeHosts
   */
  function getExcludeHosts($allHosts, $excludeHosts) {
    $result = array();
    $found = FALSE;
    foreach ($allHosts as $host) {
      foreach($excludeHosts as $exclude) {
        if ($host == $exclude) {
          $found = TRUE;
          break;
        }
        $found = FALSE;
      }
      if (!$found) {
        array_push($result, $host);
      }
    }
    return $result;
  }

  /**
   * Return a list of slaves given what machines
   * masters are running on given the set of all host
   * and services enabled.
   */
  function getSlaveList($allHosts, $masterToHost, $services) {
    $result = array();
    $numNodes = sizeof($allHosts);
    if ($numNodes == 1) {
      array_push($result, $allHosts[0]);
      return $result;
    }
    if ($numNodes <= 5) {
      /* all slaves except for the namenode */
      $excludeList = $this->getExcludeHosts($allHosts, array($masterToHost["NAMENODE"]));
      return $excludeList;
    }
    if ($numNodes > 5) {
      /* all slaves except for the namenode/JT/Hbase master */
      $excludeHosts = array();
      array_push($excludeHosts, $masterToHost["NAMENODE"]);
      array_push($excludeHosts, $masterToHost["JOBTRACKER"]);
      if (array_key_exists("HBASE", $services)) {
        array_push($excludeHosts, $masterToHost["HBASE_MASTER"]);
      }
      $excludeList = $this->getExcludeHosts($allHosts, $excludeHosts);
      return $excludeList;
    }
  }

  /**
   * convert hostInfo to a flat list
   */
  function convertHostInfoToList($hostsInfo) {
    $result = array();
    foreach($hostsInfo as $host) {
      array_push($result, $host["hostName"]);
    }
    return $result;
  }

  /**
   * Function to update the DB with roles on what user has selected.
   * This also selects the DataNodes/TaskTrackers/RegionServers based
   * on what services were picked.
   * @return Error or not
   *     array("result" => $result, "error" => $error)
   * @param clusterName name of the cluster
   * @param db the database being used
   * @param masterToHost the master to Host mapping that the user selected
   *          array("componentName" => hostName")
   */
  public function updateDBWithRoles($clusterName, $db, $masterToHost) {
    $return = array();
    $return["result"] = 0;
    $return["error"] = "";
    $this->logger->log_error("All info: ".$clusterName."\n "
        .json_encode($masterToHost));
    $allHostsDBInfo = $db->getAllHostsInfo($clusterName, "", array());
    if ($allHostsDBInfo["result"] != 0) {
      $this->logger->log_error("Issue getting all hosts info ".$allHostsDBInfo["error"]);
      $return["result"] = $allHostsDBInfo["result"];
      $return["error"] = $allHostsDBInfo["error"];
      return $return;
    }
    $allHosts_t = $allHostsDBInfo["hosts"];
    /* get all enabled services */
    $servicesDBInfo = $db->getAllServicesInfo($clusterName);
    if ($servicesDBInfo["result"] != 0) {
      $this->logger->log_error("Issue getting all services enabled ".$allHostsDBInfo["error"]);
      $return["result"] = $servicesDBInfo["result"];
      $return["error"] = $servicesDBInfo["error"];
      return $return;
    }
    $services_tmp = $servicesDBInfo["services"];
    $services = $this->filterEnabledServices($services_tmp);
    $allHosts = $this->convertHostInfoToList($allHosts_t);
    foreach($masterToHost as $componentName=>$hostName) {
      if ($componentName != "ZOOKEEPER_SERVER") {
        $this->logger->log_debug("For cluster  $clusterName setting $componentName to host $hostName");
        $db->addHostsToComponent($clusterName, $componentName, array($hostName), "ASSIGNED", "");
      }
      if ($componentName == "GANGLIA_MONITOR_SERVER") {
        $gangliaMaster = $hostName;
      }
    }

    /** make sure ganglia is added to all the masters **/
    $this->logger->log_debug("Host for Gangalia Master $gangliaMaster");
    foreach($masterToHost as $componentName=>$hostName) {
      if ($hostName != $gangliaMaster) {
        $this->logger->log_debug("Adding host $hostName for GANGLIA_MONITOR");
        $db->addHostsToComponent($clusterName, "GANGLIA_MONITOR", array($hostName), "ASSIGNED", "");
      }
    }
    // add DASHBOARD component
    $dashhostName = strtolower(exec('hostname -f'));
    $db->addHostsToComponent($clusterName, "DASHBOARD" , array($dashhostName), "ASSIGNED", "");

    $slaveList = $this->getSlaveList($allHosts, $masterToHost, $services);
    if (array_key_exists("ZOOKEEPER", $services)) {
      if (sizeof($slaveList) < 3)  {
        $this->logger->log_debug("Assigning ZOOKEEPER to Host ".array($slaveList[0]));
        $db->addHostsToComponent($clusterName, "ZOOKEEPER_SERVER", array($slaveList[0]), "ASSIGNED", "");
        $hostConfig = array ( "ZOOKEEPER_SERVER" => array( $slaveList[0] => array ( "myid" => 1 ) ) );
        $db->updateHostRoleConfigs($clusterName, $hostConfig);
      }
      else {
        $hostConfig = array( "ZOOKEEPER_SERVER" => array() );
        for ($i=0; $i < 3; $i++) {
          $hostConfig["ZOOKEEPER_SERVER"][$slaveList[$i]] = array ( "myid" => $i+1 );
          $this->logger->log_debug("Assigning ZOOKEEPER to Host ".array($slaveList[$i]));
          $db->addHostsToComponent($clusterName, "ZOOKEEPER_SERVER", array($slaveList[$i]), "ASSIGNED", "");
        }
        $db->updateHostRoleConfigs($clusterName, $hostConfig);
      }
    }
    $this->logger->log_info("Slave List \n".print_r($slaveList, true));
    $this->addSlaves($db, $slaveList, $clusterName, $services, $gangliaMaster);
    /* pick a node for gateway */
    $gateway = $slaveList[0];
    //    print_r($services);
    foreach ($services as $key=>$s) {
      $serviceName = $s["serviceName"];
      if ($serviceName != "GANGLIA" && $serviceName != "NAGIOS" && $serviceName != "MISCELLANEOUS" && $serviceName != "DASHBOARD") {
        $db->addHostsToComponent($clusterName, $serviceName."_CLIENT", array($gateway), "ASSIGNED", "");
      }
    }
    return;
  }

  /**
   * Function to select a list of nodes assuming
   * it gets all the info from the db
   * @param clustername the name of the cluster we are deploying/managing
   * @param db database from where to read, usually pass in new HMCDBAccessor("mydb.data");
   * @return mixed
   *  array (
   *       "result" => 0,
   *       "error" => "",
   *       "mastersToHosts" => array(
   *        "masterName" => array(array(
   *                     "hostname", "totalMem")) -- this in case we have multiple hosts to suggest.
   *       );
   */

  public function selectNodes($clustername, $db) {
    $return = array();
    $order = array("sortColumn" => "totalMem",
        "sortOrder" => "DESC");
    $allHostsDBInfo = $db->getAllHostsInfo($clustername, "", $order);
    if ($allHostsDBInfo["result"] != 0) {
      $this->logger->log_error("Issue getting all hosts info ".$allHostsDBInfo["error"]);
      $return["result"] = $allHostsDBInfo["result"];
      $return ["error"] = $allHostsDBInfo["error"];
      return $return;
    }
    $allHostsInfo = $allHostsDBInfo["hosts"];
    $numNodes = sizeof($allHostsInfo);
    $this->logger->log_info("Size of Cluster ".$numNodes);
    $servicesDBInfo = $db->getAllServicesInfo($clustername);
    if ($servicesDBInfo["result"] != 0) {
      $this->logger->log_error("Issue getting all services enabled ".$allHostsDBInfo["error"]);
      $return["result"] = $servicesDBInfo["result"];
      $return["error"] = $servicesDBInfo["error"];
      return $return;
    }
    $services_tmp = $servicesDBInfo["services"];
    $services = $this->filterEnabledServices($services_tmp);
    $numServices = sizeof($services);
    $result["result"] = 0;
    $this->logger->log_debug(print_r($allHostsDBInfo, true));
    $this->logger->log_debug(print_r($services,true));
    if ($numNodes == 1) {
      $result = $this->addNameNode($services, $result, $allHostsInfo[0]);
      $result = $this->addSNameNode($services, $result, $allHostsInfo[0]);
      $result = $this->addJobTracker($services, $result, $allHostsInfo[0]);
      $result = $this->addHBaseMaster($services, $result, $allHostsInfo[0]);
      $result = $this->addOozieServer($services, $result, $allHostsInfo[0]);
      $result = $this->addHiveServer($services, $result, $allHostsInfo[0]);
      $result = $this->addTempletonServer($services, $result, $allHostsInfo[0]);
      return $result;
    }
    if ( $numNodes <= 5) {
      $result = $this->addNameNode($services, $result, $allHostsInfo[0]);
      $result = $this->addSNameNode($services, $result, $allHostsInfo[1]);
      $result = $this->addJobTracker($services, $result, $allHostsInfo[1]);
      $result = $this->addHBaseMaster($services, $result, $allHostsInfo[0]);
      $result = $this->addOozieServer($services, $result, $allHostsInfo[1]);
      $result = $this->addHiveServer($services, $result, $allHostsInfo[1]);
      $result = $this->addTempletonServer($services, $result, $allHostsInfo[1]);
      return $result;
    }
    if ( $numNodes <= 30) {
      $result = $this->addNameNode($services, $result, $allHostsInfo[0]);
      $result = $this->addSNameNode($services, $result, $allHostsInfo[1]);
      $result = $this->addJobTracker($services, $result, $allHostsInfo[1]);
      $result = $this->addHBaseMaster($services, $result, $allHostsInfo[2]);
      $result = $this->addOozieServer($services, $result, $allHostsInfo[2]);
      $result = $this->addHiveServer($services, $result, $allHostsInfo[2]);
      $result = $this->addTempletonServer($services, $result, $allHostsInfo[2]);
      return $result;
    }
    if ( $numNodes > 30) {
      $result = $this->addNameNode($services, $result, $allHostsInfo[0]);
      $result = $this->addSNameNode($services, $result, $allHostsInfo[1]);
      $result = $this->addJobTracker($services, $result, $allHostsInfo[2]);
      $result = $this->addHBaseMaster($services, $result, $allHostsInfo[3]);
      $result = $this->addOozieServer($services, $result, $allHostsInfo[3]);
      $result = $this->addHiveServer($services, $result, $allHostsInfo[4]);
      $result = $this->addTempletonServer($services, $result, $allHostsInfo[4]);
      return $result;
    }
  }
}
?>