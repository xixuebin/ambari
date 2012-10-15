#
#
# Licensed to the Apache Software Foundation (ASF) under one
# or more contributor license agreements.  See the NOTICE file
# distributed with this work for additional information
# regarding copyright ownership.  The ASF licenses this file
# to you under the Apache License, Version 2.0 (the
# "License"); you may not use this file except in compliance
# with the License.  You may obtain a copy of the License at
#
#   http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing,
# software distributed under the License is distributed on an
# "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
# KIND, either express or implied.  See the License for the
# specific language governing permissions and limitations
# under the License.
#
#
class hdp2-hadoop::snamenode(
  $service_state = $hdp2::params::cluster_service_state,
  $opts = {}
) inherits hdp2-hadoop::params  
{
  $hdp2::params::service_exists['hdp2-hadoop::snamenode'] = true

  Hdp2-hadoop::Common<||>{service_states +> $service_state}
  Hdp2-hadoop::Package<||>{include_64_bit => true}
  Hdp2-hadoop::Configfile<||>{sizes +> 64}

  if ($service_state == 'no_op') {
  } elsif ($service_state in ['running','stopped','installed_and_configured','uninstalled']) {
    $fs_checkpoint_dir = $hdp2-hadoop::params::fs_checkpoint_dir
  
    #adds package, users and directories, and common hadoop configs
    include hdp2-hadoop::initialize
 
    Hdp2-Hadoop::Configfile<||>{snamenode_host => $hdp2::params::host_address}
  
    hdp2-hadoop::snamenode::create_name_dirs { $fs_checkpoint_dir: 
      service_state => $service_state
    }
    
    if ($hdp2::params::service_exists['hdp2-hadoop::namenode'] == true) {
      $create_pid_dir = false
      $create_log_dir = false
    } else {
      $create_pid_dir = true
      $create_log_dir = true
    }
    
    hdp2-hadoop::service{ 'secondarynamenode':
      ensure         => $service_state,
      user           => $hdp2-hadoop::params::hdfs_user,
      create_pid_dir => $create_pid_dir,
      create_log_dir => $create_log_dir
    }
  
    #top level does not need anchors
    Class['hdp2-hadoop'] -> Hdp2-hadoop::Service['secondarynamenode']
    Hdp2-hadoop::Namenode::Create_name_dirs<||> -> Hdp2-hadoop::Service['secondarynamenode']
  } else {
    hdp_fail("TODO not implemented yet: service_state = ${service_state}")
  }
}

define hdp2-hadoop::snamenode::create_name_dirs($service_state)
{
   $dirs = hdp_array_from_comma_list($name)
   hdp2::directory_recursive_create { $dirs :
     owner => $hdp2-hadoop::params::hdfs_user,
     mode => '0755',
     service_state => $service_state,
     force => true
  }
}