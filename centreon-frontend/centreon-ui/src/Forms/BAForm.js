/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */
/* eslint-disable import/no-named-as-default */

import React from 'react';
import { withStyles } from '@material-ui/core/styles';
import ExpansionPanel from '@material-ui/core/ExpansionPanel';
import ExpansionPanelSummary from '@material-ui/core/ExpansionPanelSummary';
import ExpansionPanelDetails from '@material-ui/core/ExpansionPanelDetails';
import Typography from '@material-ui/core/Typography';
import ExpandMoreIcon from '@material-ui/icons/ExpandMore';
import FormControlLabel from '@material-ui/core/FormControlLabel';
import IconWarning from '@material-ui/icons/Warning';
import IconError from '@material-ui/icons/Error';
import classnames from 'classnames';
import InputField from '../InputField';
import InputFieldMultiSelectValue from '../InputField/InputFieldMultiSelectValue';
import InputFieldSelect from '../InputField/InputFieldSelectCustom';
import CustomRow from '../Custom/CustomRow';
import CustomColumn from '../Custom/CustomColumn';
import IconInfo from '../Icon/IconInfo';
import MaterialSwitch from '../MaterialComponents/Switch';
import CheckboxDefault from '../MaterialComponents/Checkbox';
import ButtonCustom from '../Button/ButtonCustom';
import { MultiSelectHolder } from '..';
import MultiSelectContainer from '../MultiSelectHolder/MultiSelectContainer';
import transformStringArrayIntoObjects from '../MultiSelectPanel/helper';

const styles = (theme) => ({
  root: {
    width: '100%',
  },
  heading: {
    fontSize: theme.typography.pxToRem(15),
    fontWeight: '700',
  },
  customStyle: {
    margin: '0 !important',
    backgroundColor: 'transparent',
    boxShadow: 'none',
    borderBottom: '1px solid #bcbdc0',
    borderRadius: '0 !important',
  },
  additionalStyles: {
    display: 'block',
  },
  helperStyles: {
    paddingBottom: 0,
  },
  containerStyles: {
    padding: '10px 24px 10px 24px',
  },
});

class BAForm extends React.Component {
  render() {
    const {
      classes,
      values,
      centreonImages,
      valueChanged = () => { },
      notificationOptionChanged = () => { },
      additionalPollerChanged = () => { },
      errors,
      toggleMultiselect = () => { },
      eventHandlerCommands,
      escalations,
      timeperiods,
      kpis,
      contactGroups,
      businessViews,
      remoteServers,
      onFormFieldFocus,
      selectedMultiselect,
    } = this.props;
    return (
      <div className={classes.root}>
        <div className={classes.containerStyles}>
          <CustomRow>
            <CustomColumn customColumn="md-6">
              <InputField
                placeholder="Add a description"
                type="text"
                name="description"
                error={errors.description}
                value={values.description}
                onChange={(event) => {
                  valueChanged('description', event.target ? event.target.value : event);
                }}
              />
            </CustomColumn>
            <CustomColumn customColumn="md-6">
              <InputFieldSelect
                icons
                options={centreonImages}
                value={values.icon}
                error={errors.icon}
                customStyle="no-margin"
                onChange={(event) => {
                  valueChanged('icon', event.target ? event.target.value : event);
                }}
                domainPath="."
              />
            </CustomColumn>
          </CustomRow>
        </div>
        <ExpansionPanel
          expanded={errors.level_c || errors.level_w}
          className={classes.customStyle}
        >
          <ExpansionPanelSummary
            expandIcon={<ExpandMoreIcon />}
            aria-controls="panel1a-content"
            id="panel1a-header"
          >
            <Typography className={classes.heading}>Indicator</Typography>
          </ExpansionPanelSummary>
          <ExpansionPanelDetails
            className={classnames(
              classes.additionalStyles,
              classes.helperStyles,
            )}
          >
            <CustomRow additionalStyles={['mb-0']}>
              <CustomColumn customColumn="md-6" additionalStyles={['mb-0']}>
                <IconInfo iconText="Status calculation method" />
                <InputFieldSelect
                  value={1}
                  options={[{ id: 1, name: 'Impact', alias: 'impact' }]}
                  disabled
                />
              </CustomColumn>
              <CustomColumn customColumn="md-3" additionalStyles={['mb-0']}>
                <IconInfo iconText="Warning threshold" />
                <CustomRow additionalStyles={['mt-05', 'mb-0']}>
                  <CustomColumn
                    customColumn="md-3"
                    additionalStyles={['mt-03']}
                  >
                    <IconWarning style={{ color: '#FF9913' }} />
                  </CustomColumn>
                  <CustomColumn customColumn="md-9">
                    <InputField
                      type="number"
                      name="level_w"
                      value={values.level_w}
                      error={errors.level_w}
                      onChange={(event) => {
                        valueChanged('level_w', event.target ? event.target.value : event);
                      }}
                    />
                  </CustomColumn>
                </CustomRow>
              </CustomColumn>
              <CustomColumn customColumn="md-3" additionalStyles={['mb-0']}>
                <IconInfo iconText="Critical threshold" />
                <CustomRow additionalStyles={['mt-05']}>
                  <CustomColumn
                    customColumn="md-3"
                    additionalStyles={['mt-03']}
                  >
                    <IconError style={{ color: '#E00B3D' }} />
                  </CustomColumn>
                  <CustomColumn customColumn="md-9">
                    <InputField
                      type="text"
                      name="level_c"
                      value={values.level_c}
                      error={errors.level_c}
                      onChange={(event) => {
                        valueChanged('level_c', event.target ? event.target.value : event);
                      }}
                    />
                  </CustomColumn>
                </CustomRow>
              </CustomColumn>
            </CustomRow>
          </ExpansionPanelDetails>
          <ExpansionPanelDetails className={classes.additionalStyles}>
            <MultiSelectContainer
              label="Indicators"
              values={values.bam_kpi}
              selected={selectedMultiselect == 'bam_kpi'}
              error={errors.bam_kpi}
              onEdit={() => {
                toggleMultiselect('bam_kpi');
              }}
              emptyInfo="Click to link indicators"
            />
          </ExpansionPanelDetails>
          <ExpansionPanelDetails>
            <CustomColumn customColumn="md-12" additionalStyles={['p-0']}>
              <CustomColumn customColumn="md-12" additionalStyles={['p-0']}>
                <IconInfo iconText="Automatically inherit indicators" />
              </CustomColumn>
              <FormControlLabel
                style={{
                  marginLeft: 0,
                }}
                control={(
                  <MaterialSwitch
                    value={values.inherit_kpi_downtimes}
                    checked={values.inherit_kpi_downtimes}
                    error={errors.inherit_kpi_downtimes}
                    size="small"
                    onChange={(event, value) => {
                      valueChanged('inherit_kpi_downtimes', value);
                    }}
                  />
                )}
                label={(
                  <Typography style={{ fontSize: '13px' }}>
                    {values.inherit_kpi_downtimes ? 'Yes' : 'No'}
                  </Typography>
                )}
              />
            </CustomColumn>
          </ExpansionPanelDetails>
        </ExpansionPanel>
        <ExpansionPanel className={classes.customStyle}>
          <ExpansionPanelSummary
            expandIcon={<ExpandMoreIcon />}
            aria-controls="panel2a-content"
            id="panel2a-header"
          >
            <Typography className={classes.heading}>Business View</Typography>
          </ExpansionPanelSummary>
          <ExpansionPanelDetails className={classes.additionalStyles}>
            <MultiSelectContainer
              label="Business views"
              values={transformStringArrayIntoObjects(values.groups)}
              selected={selectedMultiselect == 'groups'}
              error={errors.groups}
              onEdit={() => {
                toggleMultiselect('groups');
              }}
              emptyInfo="Click to link business view(s)"
            />
          </ExpansionPanelDetails>
        </ExpansionPanel>
        <ExpansionPanel className={classes.customStyle}>
          <ExpansionPanelSummary
            expandIcon={<ExpandMoreIcon />}
            aria-controls="panel2a-content"
            id="panel2a-header"
          >
            <Typography className={classes.heading}>Display</Typography>
          </ExpansionPanelSummary>
          <ExpansionPanelDetails className={classes.additionalStyles}>
            <CustomRow>
              <CustomColumn customColumn="md-6">
                <IconInfo iconText="Display on remote server" />
                <InputFieldSelect
                  options={remoteServers}
                  value={values.additional_poller}
                  error={errors.additional_poller}
                  onChange={(event) => {
                    additionalPollerChanged(event);
                  }}
                />
              </CustomColumn>
              <CustomColumn customColumn="md-6">
                <IconInfo iconText="Geo-coordinates" />
                <InputField
                  placeholder="Add a geo-coordinates"
                  type="text"
                  name="ba_geo_coords"
                  error={errors.ba_geo_coords}
                  value={values.ba_geo_coords}
                  style={{
                    marginTop: '6px',
                  }}
                  onChange={(event) => {
                    valueChanged('ba_geo_coords', event.target ? event.target.value : event);
                  }}
                />
              </CustomColumn>
              <CustomColumn customColumn="md-6">
                <IconInfo iconText="Associated infrastructure view name" />
                <InputField
                  type="text"
                  name="infrastructure_view"
                  error={errors.infrastructure_view}
                  value={values.infrastructure_view}
                  style={{
                    marginTop: '6px',
                  }}
                  onChange={(event) => {
                    valueChanged('infrastructure_view', event.target ? event.target.value : event);
                  }}
                />
              </CustomColumn>
            </CustomRow>
          </ExpansionPanelDetails>
        </ExpansionPanel>
        <ExpansionPanel className={classes.customStyle}>
          <ExpansionPanelSummary
            expandIcon={<ExpandMoreIcon />}
            aria-controls="panel3a-content"
            id="panel3a-header"
          >
            <Typography className={classes.heading}>Notification</Typography>
          </ExpansionPanelSummary>
          <ExpansionPanelDetails className={classes.additionalStyles}>
            <CustomRow>
              <CustomColumn customColumn="md-6">
                <CustomColumn customColumn="md-12" additionalStyles={['p-0']}>
                  <IconInfo iconText="Activate notifications" />
                </CustomColumn>
                <FormControlLabel
                  style={{
                    marginLeft: 0,
                  }}
                  control={(
                    <MaterialSwitch
                      value={values.notifications_enabled}
                      checked={values.notifications_enabled}
                      error={errors.notifications_enabled}
                      size="small"
                      onChange={(event, value) => {
                        valueChanged('notifications_enabled', value);
                      }}
                    />
                  )}
                  label={
                    <Typography style={{ fontSize: '13px' }}>
                      {values.notifications_enabled ? 'Yes' : 'No'}
                    </Typography>
                  }
                />
              </CustomColumn>
              <CustomColumn customColumn="md-6">
                <InputField
                  placeholder="*60 seconds"
                  type="text"
                  name="test"
                  label="Interval (*60 seconds)"
                  value={values.notification_interval}
                  error={errors.notification_interval}
                  onChange={(event) => {
                    valueChanged('notification_interval', event.target ? event.target.value : event);
                  }}
                />
              </CustomColumn>
              <CustomColumn customColumn="md-6" additionalStyles={['pr-0']}>
                <CustomRow additionalStyles={['m-0']}>
                  <CustomColumn customColumn="md-6">
                    <CheckboxDefault
                      label="Recovery"
                      checked={values.notification_options.indexOf('r') > -1}
                      onChange={() => {
                        notificationOptionChanged('r');
                      }}
                      error={errors.notification_options}
                    />
                  </CustomColumn>
                  <CustomColumn customColumn="md-6">
                    <CheckboxDefault
                      label="Warning"
                      checked={values.notification_options.indexOf('w') > -1}
                      onChange={() => {
                        notificationOptionChanged('w');
                      }}
                      error={errors.notification_options}
                    />
                  </CustomColumn>
                  <CustomColumn customColumn="md-6">
                    <CheckboxDefault
                      label="Critical"
                      checked={values.notification_options.indexOf('c') > -1}
                      onChange={() => {
                        notificationOptionChanged('c');
                      }}
                      error={errors.notification_options}
                    />
                  </CustomColumn>
                  <CustomColumn customColumn="md-6">
                    <CheckboxDefault
                      label="Flapping"
                      checked={values.notification_options.indexOf('f') > -1}
                      onChange={() => {
                        notificationOptionChanged('f');
                      }}
                      error={errors.notification_options}
                    />
                  </CustomColumn>
                </CustomRow>
              </CustomColumn>
              <CustomColumn customColumn="md-6">
                <IconInfo iconText="Time period" />
                <InputFieldSelect
                  options={timeperiods}
                  value={values.notification_period}
                  error={errors.notification_period}
                  onChange={(event) => {
                    valueChanged('notification_period', event.target ? event.target.value : event);
                  }}
                />
              </CustomColumn>
              <CustomColumn customColumn="md-6">
                <InputField
                  type="number"
                  label="First notification delay"
                  value={values.first_notification_delay}
                  error={errors.first_notification_delay}
                  onChange={(event) => {
                    valueChanged('first_notification_delay', event.target ? event.target.value : event);
                  }}
                />
              </CustomColumn>
              <CustomColumn customColumn="md-6">
                <InputField
                  type="number"
                  label="Recovery notification delay"
                  value={values.recovery_notification_delay}
                  error={errors.recovery_notification_delay}
                  onChange={(event) => {
                    valueChanged('recovery_notification_delay', event.target ? event.target.value : event);
                  }}
                />
              </CustomColumn>
            </CustomRow>
          </ExpansionPanelDetails>
          <ExpansionPanelDetails className={classes.additionalStyles}>
            <MultiSelectContainer
              label="Contact groups"
              values={transformStringArrayIntoObjects(values.bam_contact)}
              selected={selectedMultiselect == 'bam_contact'}
              error={errors.bam_contact}
              onEdit={() => {
                toggleMultiselect('bam_contact');
              }}
              emptyInfo="Click to link contact group(s)"
            />
          </ExpansionPanelDetails>
          <ExpansionPanelDetails className={classes.additionalStyles}>
            <MultiSelectContainer
              label="Escalations"
              values={transformStringArrayIntoObjects(values.bam_esc)}
              selected={selectedMultiselect == 'bam_esc'}
              onEdit={() => {
                toggleMultiselect('bam_esc');
              }}
              error={errors.bam_esc}
            />
          </ExpansionPanelDetails>
        </ExpansionPanel>
        <ExpansionPanel className={classes.customStyle}>
          <ExpansionPanelSummary
            expandIcon={<ExpandMoreIcon />}
            aria-controls="panel4a-content"
            id="panel4a-header"
          >
            <Typography className={classes.heading}>Reporting</Typography>
          </ExpansionPanelSummary>
          <ExpansionPanelDetails className={classes.additionalStyles}>
            <CustomRow>
              <CustomColumn customColumn="md-6">
                <InputField
                  placeholder="(0-100%)"
                  type="text"
                  name="test"
                  label="SLA warning percentage thresholds"
                  value={values.sla_month_percent_warn}
                  error={errors.sla_month_percent_warn}
                  onChange={(event) => {
                    valueChanged('sla_month_percent_warn', event.target ? event.target.value : event);
                  }}
                />
              </CustomColumn>
              <CustomColumn customColumn="md-6">
                <InputField
                  placeholder="minutes"
                  type="text"
                  name="test"
                  label="SLA warning duration threshold"
                  value={values.sla_month_duration_warn}
                  error={errors.sla_month_duration_warn}
                  onChange={(event) => {
                    valueChanged('sla_month_duration_warn', event.target ? event.target.value : event);
                  }}
                />
              </CustomColumn>
              <CustomColumn customColumn="md-6">
                <InputField
                  placeholder="(0-100%)"
                  type="text"
                  name="test"
                  label="SLA critical percentage thresholds"
                  value={values.sla_month_percent_crit}
                  error={errors.sla_month_percent_crit}
                  onChange={(event) => {
                    valueChanged('sla_month_percent_crit', event.target ? event.target.value : event);
                  }}
                />
              </CustomColumn>
              <CustomColumn customColumn="md-6">
                <InputField
                  placeholder="minutes"
                  type="text"
                  name="test"
                  label="SLA critical duration threshold"
                  value={values.sla_month_duration_crit}
                  error={errors.sla_month_duration_crit}
                  onChange={(event) => {
                    valueChanged('sla_month_duration_crit', event.target ? event.target.value : event);
                  }}
                />
              </CustomColumn>
            </CustomRow>
            <MultiSelectContainer
              label="Extra reporting time periods used in Centreon BI indicators"
              values={transformStringArrayIntoObjects(
                values.reporting_timeperiods,
              )}
              selected={selectedMultiselect == 'reporting_timeperiods'}
              error={errors.reporting_timeperiods}
              onEdit={() => {
                toggleMultiselect('reporting_timeperiods');
              }}
              emptyInfo="Click to add time period(s)"
            />
          </ExpansionPanelDetails>
        </ExpansionPanel>
        <ExpansionPanel className={classes.customStyle}>
          <ExpansionPanelSummary
            expandIcon={<ExpandMoreIcon />}
            aria-controls="panel6a-content"
            id="panel6a-header"
          >
            <Typography className={classes.heading}>Event handler</Typography>
          </ExpansionPanelSummary>
          <ExpansionPanelDetails className={classes.additionalStyles}>
            <CustomRow additionalStyles={['w-100']}>
              <CustomColumn customColumn="md-6">
                <CustomColumn customColumn="md-12" additionalStyles={['p-0']}>
                  <IconInfo iconText="Activate event handling" />
                </CustomColumn>
                <FormControlLabel
                  style={{
                    marginLeft: 0,
                  }}
                  control={(
                    <MaterialSwitch
                      value={values.event_handler_enabled}
                      checked={values.event_handler_enabled}
                      error={errors.event_handler_enabled}
                      size="small"
                      onChange={(event, value) => {
                        valueChanged('event_handler_enabled', value);
                      }}
                    />
                  )}
                  label={
                    <Typography style={{ fontSize: '13px' }}>
                      {values.event_handler_enabled ? 'Yes' : 'No'}
                    </Typography>
                  }
                />
              </CustomColumn>
              <CustomColumn customColumn="md-6">
                <IconInfo iconText="Event handler command" />
                <InputFieldSelect
                  options={eventHandlerCommands}
                  value={values.event_handler_command}
                  error={errors.event_handler_command}
                  onChange={(event) => {
                    valueChanged('event_handler_command', event.target ? event.target.value : event);
                    // event.target ? event.target.value : event is done here instead of event.persist()
                  }}
                />
              </CustomColumn>
            </CustomRow>
          </ExpansionPanelDetails>
        </ExpansionPanel>
      </div>
    );
  }
}

export default withStyles(styles)(BAForm);
