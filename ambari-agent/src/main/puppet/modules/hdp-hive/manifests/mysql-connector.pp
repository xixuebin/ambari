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
class hdp-hive::mysql-connector()
{
  include hdp-hive::params

  $hive_lib = $hdp-hive::params::hive_lib
  $target = "${hive_lib}/mysql-connector-java.jar"
  
  anchor { 'hdp-hive::mysql-connector::begin':}

   hdp::package { 'mysql-connector' :
     require   => Anchor['hdp-hive::mysql-connector::begin']
   }

   hdp::exec { 'hive mkdir -p ${artifact_dir} ;  cp /usr/share/java/mysql-connector-java.jar  ${target}':
       command => "mkdir -p ${artifact_dir} ;  cp /usr/share/java/mysql-connector-java.jar  ${target}",
       unless  => "test -f ${target}",
       creates => $target,
       path    => ["/bin","/usr/bin/"],
       require => Hdp::Package['mysql-connector'],
       notify  =>  Anchor['hdp-hive::mysql-connector::end'],
   }

   anchor { 'hdp-hive::mysql-connector::end':}

}
