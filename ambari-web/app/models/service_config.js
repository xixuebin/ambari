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

var App = require('app');
var validator = require('utils/validator');

App.ConfigProperties = Ember.ArrayProxy.extend({
  content: require('data/config_properties').configProperties
});

App.ServiceConfig = Ember.Object.extend({
  serviceName: '',
  configCategories: [],
  configs: null,

  errorCount: function () {
    var masterErrors = this.get('configs').filterProperty('isValid', false).filterProperty('isVisible', true).get('length');
    var slaveErrors = 0;
    this.get('configCategories').forEach(function(_category){
      slaveErrors += _category.get('slaveErrorCount');
    },this);
    return masterErrors + slaveErrors;
  }.property('configs.@each.isValid', 'configs.@each.isVisible', 'configCategories.@each.slaveErrorCount')
});

App.ServiceConfigCategory = Ember.Object.extend({
  name: null,

  slaveConfigs: null,
  primaryName: function () {
    switch (this.get('name')) {
      case 'DataNode':
        return 'DATANODE';
        break;
      case 'TaskTracker':
        return 'TASKTRACKER';
        break;
      case 'RegionServer':
        return 'HBASE_REGIONSERVER';
    }
  }.property('name'),


  isForMasterComponent: function () {
    var masterServices = [ 'NameNode', 'SNameNode', 'JobTracker', 'HBase Master', 'Oozie Master',
      'Hive Metastore', 'Templeton Server', 'ZooKeeper Server', 'Nagios', 'Ganglia' ];

    return (masterServices.contains(this.get('name')));
  }.property('name'),

  isForSlaveComponent: function () {
    return this.get('name') === 'DataNode' || this.get('name') === 'TaskTracker' ||
      this.get('name') === 'RegionServer';
  }.property('name'),

  slaveErrorCount: function () {
    var length = 0;
    if (this.get('slaveConfigs.groups')) {
      this.get('slaveConfigs.groups').forEach(function (_group) {
        length += _group.get('errorCount');
      }, this);
    }
    return length;
  }.property('slaveConfigs.groups.@each.errorCount')
});


App.SlaveConfigs = Ember.Object.extend({
  componentName: null,
  displayName: null,
  hosts: null,
  groups: null
});

App.Group = Ember.Object.extend({
  name: null,
  hostNames: null,
  properties: null,
  errorCount: function () {
    if (this.get('properties')) {
      return this.get('properties').filterProperty('isValid', false).filterProperty('isVisible', true).get('length');
    }
  }.property('properties.@each.isValid', 'properties.@each.isVisible')
});


App.ServiceConfigProperty = Ember.Object.extend({

  id: '', //either 'puppet var' or 'site property'
  name: '',
  displayName: '',
  value: '',
  defaultValue: '',
  defaultDirectory: '',
  description: '',
  displayType: 'string', // string, digits, number, directories, custom
  unit: '',
  category: 'General',
  isRequired: true, // by default a config property is required
  isReconfigurable: true, // by default a config property is reconfigurable
  isEditable: true, // by default a config property is editable
  errorMessage: '',
  serviceConfig: null, // points to the parent App.ServiceConfig object
  filename: '',

  init: function () {
    if (this.get('id') === 'puppet var') {
      this.set('value', this.get('defaultValue'));
    }
    // TODO: remove mock data
  },

  initialValue: function () {
    var masterComponentHostsInDB = App.db.getMasterComponentHosts();
    //console.log("value in initialvalue: " + JSON.stringify(masterComponentHostsInDB));
    var hostsInfo = App.db.getHosts(); // which we are setting in installerController in step3.
    var isOnlyFirstOneNeeded = true;
    switch (this.get('name')) {
      case 'namenode_host':
        var temp = masterComponentHostsInDB.findProperty('component', 'NAMENODE');
        this.set('value', temp.hostName);
        break;
      case 'snamenode_host':
        this.set('value', masterComponentHostsInDB.findProperty('component', 'SECONDARY_NAMENODE').hostName);
        break;
      case 'jobtracker_host':
        this.set('value', masterComponentHostsInDB.findProperty('component', 'JOBTRACKER').hostName);
        break;
      case 'hbasemaster_host':
        this.set('value', masterComponentHostsInDB.findProperty('component', 'HBASE_MASTER').hostName);
        break;
      case 'hivemetastore_host':
        this.set('value', masterComponentHostsInDB.findProperty('component', 'HIVE_SERVER').hostName);
        break;
      case 'hive_ambari_host':
        this.set('value', masterComponentHostsInDB.findProperty('component', 'HIVE_SERVER').hostName);
        break;
      case 'oozieserver_host':
        this.set('value', masterComponentHostsInDB.findProperty('component', 'OOZIE_SERVER').hostName);
        break;
      case 'oozie_ambari_host':
        this.set('value', masterComponentHostsInDB.findProperty('component', 'OOZIE_SERVER').hostName);
        break;
      case 'zookeeperserver_hosts':
        this.set('value', masterComponentHostsInDB.findProperty('component', 'ZOOKEEPER_SERVER').hostName);
        break;
      case 'dfs_name_dir':
      case 'dfs_data_dir':
      case 'mapred_local_dir':
         this.unionAllMountPoints( !isOnlyFirstOneNeeded );
      break;
      case 'fs_checkpoint_dir':
      case 'zk_data_dir' :
        this.unionAllMountPoints( isOnlyFirstOneNeeded );
        break;
    }
  },
  unionAllMountPoints : function( isOnlyFirstOneNeeded ){
    var datanode_hostname = '';
    var mountPointsPerHost = [];
    var mountPointsAsRoot =   [];
    var mountPointsAsBoot =   [];
    var mountPointsAsHome =   [];
    var mountPointsFortmpfs =   [];
    var mountPointsForVboxsf =   [];
    var masterComponentHostsInDB = App.db.getMasterComponentHosts();
    var slaveComponentHostsInDB = App.db.getSlaveComponentHosts();
    var hostsInfo = App.db.getHosts(); // which we are setting in installerController in step3.
    var temp = '';
    var setOfHostNames = [];
    switch(this.get('name')){
      case 'dfs_name_dir':
        var components = masterComponentHostsInDB.filterProperty('component', 'NAMENODE');
        components.forEach(function(component){
          setOfHostNames.push(component.hostName);
        },this);
        break;
      case 'fs_checkpoint_dir':
        var components = masterComponentHostsInDB.filterProperty('component', 'SECONDARY_NAMENODE');
        components.forEach(function(component){
          setOfHostNames.push(component.hostName);
        },this);
        break;
      case 'dfs_data_dir':
        temp = slaveComponentHostsInDB.findProperty('componentName', 'DATANODE');
        temp.hosts.forEach(function(host){
          setOfHostNames.push(host.hostName);
        },this);
        break;

      case 'mapred_local_dir':
        temp = slaveComponentHostsInDB.findProperty('componentName', 'TASKTRACKER');
        temp.hosts.forEach(function(host){
          setOfHostNames.push(host.hostName);
        },this);
        break;

      case 'zk_data_dir':
        var components = masterComponentHostsInDB.filterProperty('component', 'ZOOKEEPER_SERVER');
        components.forEach(function(component){
          setOfHostNames.push(component.hostName);
        },this);
        break;
    }

    var allMountPoints = [];
    for(var i = 0; i < setOfHostNames.length; i++ ){
      datanode_hostname = setOfHostNames[i];
      mountPointsPerHost = hostsInfo[datanode_hostname].disk_info;
      mountPointsAsRoot =   mountPointsPerHost.filterProperty('mountpoint', '/');
      mountPointsAsBoot =   mountPointsPerHost.filterProperty('mountpoint', '/boot');
      mountPointsAsHome =   mountPointsPerHost.filterProperty('mountpoint', '/home');
      mountPointsFortmpfs =   mountPointsPerHost.filterProperty('type', 'tmpfs');
      mountPointsForVboxsf =   mountPointsPerHost.filterProperty('type', 'vboxsf');

      var mountPointsToBeIgnored = [];
      mountPointsToBeIgnored.push(mountPointsAsRoot);
      mountPointsToBeIgnored.push(mountPointsAsBoot);
      mountPointsToBeIgnored.push(mountPointsAsHome);

      mountPointsFortmpfs.forEach(function(mpoint){
        mountPointsToBeIgnored.push(mpoint);
      },this);
      mountPointsForVboxsf.forEach(function(mpoint){
        mountPointsToBeIgnored.push(mpoint);
      },this);

      mountPointsPerHost = mountPointsPerHost.removeAll(mountPointsToBeIgnored);

      mountPointsPerHost.forEach(function(mPoint){
        allMountPoints.push(mPoint);
      },this);
    }
    if( allMountPoints.length == 0 ){
      allMountPoints.push(mountPointsAsRoot[0]);
    }
    this.set('value','');
    if( !isOnlyFirstOneNeeded ){
      allMountPoints.forEach(function(eachDrive){
          var mPoint = this.get('value');
          if(!mPoint)
            mPoint = "";
          mPoint += ( eachDrive.mountpoint + this.get('defaultDirectory') + "\n" );
          this.set('value', mPoint);
          this.set('defaultValue', mPoint );
      },this);
    } else{
      this.set('value', allMountPoints[0].mountpoint + this.get('defaultDirectory') );
      this.set('defaultValue', allMountPoints[0].mountpoint + this.get('defaultDirectory')  );
    }

  },
  isValid: function () {
    return this.get('errorMessage') === '';
  }.property('errorMessage'),

  viewClass: function () {
    switch (this.get('displayType')) {
      case 'checkbox':
        return App.ServiceConfigCheckbox;
      case 'password':
        return App.ServiceConfigPasswordField;
      case 'combobox':
        return App.ServiceConfigComboBox;
      case 'radio button':
        return App.ServiceConfigRadioButtons;
        break;
      case 'directories':
        return App.ServiceConfigTextArea;
        break;
      case 'custom':
        return App.ServiceConfigBigTextArea;
      case 'masterHost':
        return App.ServiceConfigMasterHostView;
      case 'masterHosts':
        return App.ServiceConfigMasterHostsView;
      case 'slaveHosts':
        return App.ServiceConfigSlaveHostsView;
      default:
        if (this.get('unit')) {
          return App.ServiceConfigTextFieldWithUnit;
        } else {
          return App.ServiceConfigTextField;
        }
    }
  }.property('displayType'),

  validate: function () {

    var value = this.get('value');

    var isError = false;

    if (this.get('isRequired')) {
      if (typeof value === 'string' && value.trim().length === 0) {
        this.set('errorMessage', 'This is required');
        isError = true;
      }
    }

    if (!isError) {
      switch (this.get('displayType')) {
        case 'int':
          if (!validator.isValidInt(value)) {
            this.set('errorMessage', 'Must contain digits only');
            isError = true;
          }
          break;
        case 'float':
          if (!validator.isValidFloat(value)) {
            this.set('errorMessage', 'Must be a valid number');
            isError = true;
          }
          break;
        case 'checkbox':
          break;
        case 'directories':
          break;
        case 'custom':
          break;
        case 'email':
          if (!validator.isValidEmail(value)) {
            this.set('errorMessage', 'Must be a valid email address');
            isError = true;
          }
          break;
        case 'password':
          // retypedPassword is set by the retypePasswordView child view of App.ServiceConfigPasswordField
          if (value !== this.get('retypedPassword')) {
            this.set('errorMessage', 'Passwords do not match');
            isError = true;
          }
      }
    }
    if (!isError) {
      this.set('errorMessage', '');
    }
  }.observes('value', 'retypedPassword')

});

