/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/jsx-no-bind */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';
import ReactDOM from 'react-dom';
import classnames from 'classnames';
import Button from '@material-ui/core/Button';
import ArrowForward from '@material-ui/icons/ArrowForwardIos';
import ArrowBack from '@material-ui/icons/ArrowBackIos';
import styles from './panels.scss';
import styles2 from './PanelItem/panel-item.scss';
import PanelItem from './PanelItem';
import PanelHeaderTitle from './PanelHeaderTitle';
import IconPowerSettings from '../MaterialComponents/Icons/IconPowerSettings';
import IconPowerSettingsDisable from '../MaterialComponents/Icons/IconPowerSettingsDisable';
import IconAttach from '../MaterialComponents/Icons/IconAttach';
import BAForm from '../Forms/BAForm';
import IconCloseNew from '../MaterialComponents/Icons/IconClose';
import InputField from '../InputField';
import TableDefault from '../Table/TableDefault';
import MultiSelectPanel from '../MultiSelectPanel';
import BAModel from '../Mocks/oneBa';
import TABLE_COLUMN_TYPES from '../Table/ColumnTypes';
import transformStringArrayIntoObjects from '../MultiSelectPanel/helper';
import Loader from '../Loader';
import {requiredValidator} from '../Forms/validators';
import LinearProgress from '@material-ui/core/LinearProgress';

const multiselectsConfiguration = {
  reporting_timeperiods: {
    dataKey: 'timeperiods',
    tableConfiguration: [
      {
        id: 'name',
        numeric: false,
        disablePadding: true,
        label: 'Name',
        type: TABLE_COLUMN_TYPES.string,
      },
      {
        id: 'alias',
        numeric: false,
        label: 'Alias',
        type: TABLE_COLUMN_TYPES.string,
      },
    ],
    label: 'Manage extra reporting time periods used in Centreon BI indicators',
    onlySelectedSwitcher: true,
    multiSelectNeedsTransformation: true,
  },
  bam_kpi: {
    dataKey: 'kpis',
    tableConfiguration: [
      {
        id: 'name',
        numeric: false,
        disablePadding: true,
        label: 'Name',
        subValue:'type',
        type: TABLE_COLUMN_TYPES.widthVariation,
      },
    ],
    label: 'Manage indicators',
    indicatorsEditor: true,
    onlySelectedSwitcher: true,
    multiSelectNeedsTransformation: false,
  },
  groups: {
    dataKey: 'businessViews',
    tableConfiguration: [
      {
        id: 'name',
        numeric: false,
        label: 'Name',
        type: TABLE_COLUMN_TYPES.string,
      },
      {
        id: 'description',
        numeric: false,
        label: 'Description',
        type: TABLE_COLUMN_TYPES.string,
      },
      {
        id: 'visible',
        numeric: false,
        label: 'Visible',
        type: TABLE_COLUMN_TYPES.boolean,
      },
    ],
    label: 'Manage Business views',
    onlySelectedSwitcher: true,
    multiSelectNeedsTransformation: true,
  },
  bam_contact: {
    dataKey: 'contactGroups',
    tableConfiguration: [
      {
        id: 'name',
        numeric: false,
        label: 'Name',
        type: TABLE_COLUMN_TYPES.string,
      },
      {
        id: 'activate',
        numeric: false,
        label: 'Activate',
        type: TABLE_COLUMN_TYPES.boolean,
      },
    ],
    label: 'Manage contact groups',
    onlySelectedSwitcher: true,
    multiSelectNeedsTransformation: true,
  },
  bam_esc: {
    dataKey: 'escalations',
    tableConfiguration: [
      {
        id: 'name',
        numeric: false,
        label: 'Name',
        type: TABLE_COLUMN_TYPES.string,
      },
    ],
    label: 'Manage escalations',
    onlySelectedSwitcher: true,
    multiSelectNeedsTransformation: true,
  },
};

class BAPanel extends React.Component {
  state = {
    multiselectActive: false,
    multiSelectKey: null,
    activeMultiselectKey: '',
    nameEditingToggled: false,
    formModified: false,
    successfullySaved:false,
    errorfullySaved:false
  };

  toggleMultiselect = (multiSelectKey) => {
    if (!multiSelectKey) {
      this.setState({
        multiselectActive: false,
        multiSelectKey: null,
      });
    } else {
      this.setState({
        multiSelectKey,
        multiselectActive: true,
      });
    }
  };

  toggleNameEditing = () => {
    const { nameEditingToggled } = this.state;
    this.setState({
      nameEditingToggled: !nameEditingToggled,
    });
  };

  focusNameEditInput(component) {
    if (component) {
      ReactDOM.findDOMNode(component).focus();
    }
  }

  onFormModified = () => {
    this.setState({
      formModified: true
    });
  }

  valueChanged = (key,event,validator) => {
    const {valueChanged = () => {}} = this.props;
    const {formModified} = this.state;
    if(!formModified){
      this.setState({
        formModified:true
      },()=>{
        valueChanged(key,event,validator)
      })
    }else{
      valueChanged(key,event,validator)
    }
  }

  onSave = () => {
    const {onSave = () => {}} = this.props;
    const savingPromise = onSave();
    savingPromise.then(()=>{
      this.setState({successfullySaved:true}, ()=>{
        setTimeout(()=>{
          this.setState({
            successfullySaved:false
          })
        },1000)
      })
    }).catch(()=>{
      this.setState({errorfullySaved:true}, ()=>{
      setTimeout(()=>{
        this.setState({
          errorfullySaved:false
        })
      },1000)
    })
    });
    this.setState({
      formModified:false
    });
  }

  render() {
    const {
      customClass,
      active,
      onClose,
      values = BAModel.result,
      errors = {},
      notificationOptionChanged = () => { },
      additionalPollerChanged = () => { },
      centreonImages,
      eventHandlerCommands,
      escalations,
      timeperiods,
      timeperiodsForSelect,
      kpis,
      impacts,
      contactGroups,
      businessViews,
      onSearchMultiselect,
      onPaginateMultiselect,
      onPaginationLimitChangedMultiselect,
      onSortMultiselect,
      multiSelectFilters,
      saving,
      onlySelectedChange,
      onlySelectedFilter,
    } = this.props;
    const {
      multiselectActive,
      nameEditingToggled,
      multiSelectKey,
      formModified,
      errorfullySaved,
      successfullySaved
    } = this.state;
    if (!values) return null;

    return (
      <div
        className={classnames(
          styles.panels,
          styles[customClass || ''],
          styles[active ? 'panels-active' : ''],
          styles[multiselectActive ? 'panels-second-active' : ''],
        )}
      >
      {saving ?
      <div style={{
        flexGrow: 1,
      }}>
        <LinearProgress />
      </div> : null}
      
        <div className={classnames(styles['panels-dialog'])}>
          <div className={classnames(styles['panels-inner'])}>
            <div className={classnames(styles['panels-header'])}>
              {values.icon ? (
                <IconAttach
                  uploadedImage
                  imgSource={
                    centreonImages.find((x) => x.id == values.icon)
                      ? centreonImages.find((x) => x.id == values.icon).preview
                      : ''
                  }
                />
              ) : (
                  <IconAttach defaultImage />
                )}
              {values.activate ? (
                <IconPowerSettings
                  onClick={() => {
                    this.valueChanged('activate', false);
                  }}
                />
              ) : (
                  <IconPowerSettingsDisable
                    onClick={() => {
                      this.valueChanged('activate', true);
                    }}
                  />
                )}

              <InputField
                placeholder="Click here to add name"
                type="text"
                name="name"
                value={values.name}
                inputSize="header"
                onChange={(event) => {
                  this.valueChanged('name', event.target ? event.target.value : event, requiredValidator(event,'Compulsory field'))
                }}
                style={{
                  width: multiselectActive ? '784px' : '285px',
                  borderBottom: '1px solid'
                }}
                className={classnames(
                  styles['panels-header-input'],
                  errors.name ? styles['has-error'] : '',
                )}
              />

              <IconCloseNew onClick={onClose} />
              <Button
                variant="contained"
                color={saving || !formModified || Object.keys(errors).length > 0 ? 'disabled' : "primary"}
                style={{
                  position: 'absolute',
                  width:'75px',
                  right: 60,
                  top: 9,
                  ...((!saving && formModified && Object.keys(errors).length < 1) && {backgroundColor: '#0072CE'}),
                  fontSize: 11,
                  cursor: saving || !formModified || Object.keys(errors).length > 0 ? 'not-allowed' : 'pointer'
                }}
                onClick={saving || !formModified || Object.keys(errors).length > 0 ? ()=>{} : this.onSave}
              >
                {saving ? 'Saving' : successfullySaved ? 'Saved!': errorfullySaved ? 'Error!' : 'Save'}
              </Button>
            </div>
            <div className={classnames(styles['panels-body'])}>
              <PanelItem
                panelItemType="big"
                panelItemShow={multiselectActive ? 'panel-item-show-big' : ''}
              >
                <div className={classnames(styles2['panel-item-inner'])}>
                  <BAForm
                    values={values}
                    errors={errors}
                    centreonImages={centreonImages}
                    eventHandlerCommands={eventHandlerCommands}
                    escalations={escalations}
                    timeperiods={timeperiodsForSelect.entities}
                    kpis={kpis}
                    contactGroups={contactGroups}
                    businessViews={businessViews}
                    remoteServers={[]}
                    notificationOptionChanged={notificationOptionChanged}
                    toggleMultiselect={this.toggleMultiselect}
                    valueChanged={this.valueChanged}
                    additionalPollerChanged={additionalPollerChanged}
                    selectedMultiselect={multiSelectKey}
                  />
                </div>
                <span
                  className={classnames(
                    styles['panels-arrow'],
                    multiselectActive ? styles['panels-arrow-right'] : '',
                  )}
                  {...(multiselectActive && {
                    onClick: () => {
                      this.toggleMultiselect(false);
                    },
                  })}
                >
                  {multiselectActive ? <ArrowForward /> : null}
                </span>
              </PanelItem>
              <MultiSelectPanel
                styles={styles2}
                active={multiselectActive}
                title={
                  multiselectsConfiguration[multiSelectKey]
                    ? multiselectsConfiguration[multiSelectKey].label
                    : ''
                }
                data={
                  multiselectsConfiguration[multiSelectKey]
                    ? this.props[
                      multiselectsConfiguration[multiSelectKey].dataKey
                    ].entities
                    : []
                }
                tableConfiguration={
                  multiselectsConfiguration[multiSelectKey]
                    ? multiselectsConfiguration[multiSelectKey]
                      .tableConfiguration
                    : []
                }
                onSearch={(value) => {
                  onSearchMultiselect(
                    multiselectsConfiguration[multiSelectKey].dataKey,
                    value,
                  );
                }}
                key={multiSelectKey}
                onPaginate={(event, page) => {
                  onPaginateMultiselect(
                    multiselectsConfiguration[multiSelectKey].dataKey,
                    event,
                    page,
                  );
                }}
                onPaginationLimitChanged={(value) => {
                  onPaginationLimitChangedMultiselect(
                    multiselectsConfiguration[multiSelectKey].dataKey,
                    value,
                  );
                }}
                onSort={(value) => {
                  onSortMultiselect(
                    multiselectsConfiguration[multiSelectKey].dataKey,
                    value,
                  );
                }}
                currentPage={
                  multiselectsConfiguration[multiSelectKey]
                    ? multiSelectFilters[
                      multiselectsConfiguration[multiSelectKey].dataKey
                    ].offset != 0
                      ? multiSelectFilters[
                        multiselectsConfiguration[multiSelectKey].dataKey
                      ].offset /
                      multiSelectFilters[
                        multiselectsConfiguration[multiSelectKey].dataKey
                      ].limit
                      : 0
                    : 0
                }
                totalRows={
                  multiselectsConfiguration[multiSelectKey]
                    ? this.props[
                      multiselectsConfiguration[multiSelectKey].dataKey
                    ].pagination.total
                    : 0
                }
                currentlySelected={
                  values[multiSelectKey]
                    ? multiselectsConfiguration[multiSelectKey]
                      .multiSelectNeedsTransformation
                      ? transformStringArrayIntoObjects(values[multiSelectKey])
                      : values[multiSelectKey]
                    : []
                }
                paginationLimit={
                  multiselectsConfiguration[multiSelectKey]
                    ? multiSelectFilters[
                      multiselectsConfiguration[multiSelectKey].dataKey
                    ].limit
                    : 0
                }
                onSelect={(selected) => {
                  this.valueChanged(multiSelectKey, selected);
                }}
                nameIdPaired={
                  multiselectsConfiguration[multiSelectKey]
                    ? multiselectsConfiguration[multiSelectKey]
                      .multiSelectNeedsTransformation
                    : true
                }
                indicatorsEditor={
                  multiselectsConfiguration[multiSelectKey]
                    ? multiselectsConfiguration[multiSelectKey].indicatorsEditor
                    : false
                }
                impacts={impacts}
                onlySelectedChange={onlySelectedChange}
                onlySelectedFilter={onlySelectedFilter}
                onlySelectedSwitcher={
                  multiselectsConfiguration[multiSelectKey]
                    ? multiselectsConfiguration[multiSelectKey]
                      .onlySelectedSwitcher
                    : false
                }
              />
            </div>
          </div>
        </div>
      </div>
    );
  }
}

export default BAPanel;
