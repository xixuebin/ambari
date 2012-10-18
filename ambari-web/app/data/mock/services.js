/**
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

module.exports = [
  {
    serviceName: 'HDFS',
    displayName: 'HDFS',
    isDisabled: true,
    isSelected: true,
    description: Em.I18n.t('services.hdfs.description')
  },
  {
    serviceName: 'MAPREDUCE',
    displayName: 'MapReduce',
    isDisabled: false,
    isSelected: true,
    description: Em.I18n.t('services.mapreduce.description')
  },
  {
    serviceName: 'NAGIOS',
    displayName: 'Nagios',
    isDisabled: false,
    isSelected: true,
    description: Em.I18n.t('services.nagios.description')
  },
  {
    serviceName: 'GANGLIA',
    displayName: 'Ganglia',
    isDisabled: false,
    isSelected: true,
    description: Em.I18n.t('services.ganglia.description')
  },
  {
    serviceName: 'HIVE',
    displayName: 'Hive + HCatalog',
    isDisabled: false,
    isSelected: true,
    description: Em.I18n.t('services.hive.description')
  },
  {
    serviceName: 'HBASE',
    displayName: 'HBase + ZooKeeper',
    isDisabled: false,
    isSelected: true,
    description: Em.I18n.t('services.hbase.description')
  },
  {
    serviceName: 'PIG',
    displayName: 'Pig',
    isDisabled: false,
    isSelected: true,
    description: Em.I18n.t('services.pig.description')
  },
  {
    serviceName: 'SQOOP',
    displayName: 'Sqoop',
    isDisabled: false,
    isSelected: true,
    description: Em.I18n.t('services.sqoop.description')
  },
  {
    serviceName: 'OOZIE',
    displayName: 'Oozie',
    isDisabled: false,
    isSelected: true,
    description: Em.I18n.t('services.oozie.description')
  },
  {
    serviceName: 'ZOOKEEPER',
    isDisabled: false,
    isSelected: true,
    isHidden: true
  },
  {
    serviceName: 'HCATALOG',
    isDisabled: false,
    isSelected: true,
    isHidden: true
  }
]