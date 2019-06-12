import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import ExpansionPanel from '@material-ui/core/ExpansionPanel';
import ExpansionPanelSummary from '@material-ui/core/ExpansionPanelSummary';
import ExpansionPanelDetails from '@material-ui/core/ExpansionPanelDetails';
import Typography from '@material-ui/core/Typography';
import ExpandMoreIcon from '@material-ui/icons/ExpandMore';
import FormControlLabel from '@material-ui/core/FormControlLabel';
import IconWarning from '@material-ui/icons/Warning';
import IconError from '@material-ui/icons/Error';
import InputField from '../../InputField';
import InputFieldMultiSelect from '../../InputField/InputFieldSelectCustom';
import CustomRow from '../../Custom/CustomRow';
import CustomColumn from '../../Custom/CustomColumn';
import IconInfo from '../../Icon/IconInfo';
import MaterialSwitch from '../Switch';

const useStyles = makeStyles(theme => ({
  root: {
    width: '100%',
    overflow: 'hidden',
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
  }
}));

function Accordion() {
  const classes = useStyles();

  return (
    <div className={classes.root}>
      <ExpansionPanel className={classes.customStyle}>
        <ExpansionPanelSummary
          expandIcon={<ExpandMoreIcon />}
          aria-controls="panel1a-content"
          id="panel1a-header"
        >
          <Typography className={classes.heading}>Configuration</Typography>
        </ExpansionPanelSummary>
        <ExpansionPanelDetails>
          <CustomRow>
            <CustomColumn customColumn="md-6">
              <InputField placeholder="Writing" type="text" name="test" />
            </CustomColumn>
            <CustomColumn customColumn="md-6">
              <InputFieldMultiSelect options={[
                {id:"1", name:"24x7", alias:"Always"}
                ,
                {id:"2", name:"none", alias:"Never"}
                ,
                {id:"3", name:"nonworkhours", alias:"Non-Work Hours"}
                ,
                {id:"4", name:"workhours", alias:"Work hours"}
                ]}
                customStyle="no-margin"
              />
            </CustomColumn>
            <CustomColumn customColumn="md-6">
              <CustomColumn customColumn="md-12" additionalStyles={["p-0"]}>
                <IconInfo iconText="Automatically inherit KPI downtimes" />
              </CustomColumn>
              <FormControlLabel
                control={
                  <MaterialSwitch />
                }
                label="Enable"
              />
            </CustomColumn>
            <CustomColumn customColumn="md-6">
              <IconInfo iconText="Display on remote server" />
              <InputFieldMultiSelect options={[
                {id:"1", name:"24x7", alias:"Always"}
                ,
                {id:"2", name:"none", alias:"Never"}
                ,
                {id:"3", name:"nonworkhours", alias:"Non-Work Hours"}
                ,
                {id:"4", name:"workhours", alias:"Work hours"}
                ]}
              />
            </CustomColumn>
          </CustomRow>
        </ExpansionPanelDetails>
      </ExpansionPanel>
      <ExpansionPanel className={classes.customStyle}>
        <ExpansionPanelSummary
          expandIcon={<ExpandMoreIcon />}
          aria-controls="panel2a-content"
          id="panel2a-header"
        >
          <Typography className={classes.heading}>Indicator</Typography>
        </ExpansionPanelSummary>
        <ExpansionPanelDetails>
          <CustomRow>
            <CustomColumn customColumn="md-6">
              <IconInfo iconText="Status calculation method" />
              <InputFieldMultiSelect options={[
                {id:"1", name:"24x7", alias:"Always"}
                ,
                {id:"2", name:"none", alias:"Never"}
                ,
                {id:"3", name:"nonworkhours", alias:"Non-Work Hours"}
                ,
                {id:"4", name:"workhours", alias:"Work hours"}
                ]}
              />
            </CustomColumn>
            <CustomColumn customColumn="md-3">
            <IconInfo iconText="Warning threshold" />
            <CustomRow additionalStyles={["mt-05"]}>
              <CustomColumn customColumn="md-3" additionalStyles={["mt-03"]}>
                <IconWarning style={{ color: '#FF9913' }} />
              </CustomColumn>
              <CustomColumn customColumn="md-9">
                <InputField type="text" name="test" />
              </CustomColumn>
            </CustomRow>
            </CustomColumn>
            <CustomColumn customColumn="md-3">
              <IconInfo iconText="Critical threshold" />
              <CustomRow additionalStyles={["mt-05"]}>
                <CustomColumn customColumn="md-3" additionalStyles={["mt-03"]}>
                  <IconError style={{color: '#E00B3D'}} />
                </CustomColumn>
                <CustomColumn customColumn="md-9">
                  <InputField type="text" name="test" />
                </CustomColumn>
              </CustomRow>
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
          <Typography className={classes.heading}>Busniness View</Typography>
        </ExpansionPanelSummary>
        <ExpansionPanelDetails>
          <Typography>
            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Suspendisse malesuada lacus ex,
            sit amet blandit leo lobortis eget.
          </Typography>
        </ExpansionPanelDetails>
      </ExpansionPanel>
      <ExpansionPanel className={classes.customStyle}>
      <ExpansionPanelSummary
          expandIcon={<ExpandMoreIcon />}
          aria-controls="panel4a-content"
          id="panel4a-header"
        >
          <Typography className={classes.heading}>Notification</Typography>
        </ExpansionPanelSummary>
        <ExpansionPanelDetails>
          <CustomRow>
            <CustomColumn customColumn="md-6">
              <CustomColumn customColumn="md-12" additionalStyles={["p-0"]}>
                <IconInfo iconText="View notifications" />
              </CustomColumn>
              <FormControlLabel
                control={
                  <MaterialSwitch />
                }
                label="Enable"
              />
            </CustomColumn>
            <CustomColumn customColumn="md-6">
              <IconInfo iconText="Interval" />
              <InputField placeholder="*60 seconds" type="text" name="test" />
            </CustomColumn>
            <CustomColumn customColumn="md-6">
              <IconInfo iconText="Options" />
              <InputFieldMultiSelect options={[
                {id:"1", name:"24x7", alias:"Always"}
                ,
                {id:"2", name:"none", alias:"Never"}
                ,
                {id:"3", name:"nonworkhours", alias:"Non-Work Hours"}
                ,
                {id:"4", name:"workhours", alias:"Work hours"}
                ]}
              />
            </CustomColumn>
            <CustomColumn customColumn="md-6">
              <IconInfo iconText="Time period" />
              <InputFieldMultiSelect options={[
                {id:"1", name:"24x7", alias:"Always"}
                ,
                {id:"2", name:"none", alias:"Never"}
                ,
                {id:"3", name:"nonworkhours", alias:"Non-Work Hours"}
                ,
                {id:"4", name:"workhours", alias:"Work hours"}
                ]}
              />
            </CustomColumn>
          </CustomRow>
        </ExpansionPanelDetails>
      </ExpansionPanel>
    </div>
  );
}

export default Accordion;
