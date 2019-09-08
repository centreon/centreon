import React, { Component } from 'react';
import classnames from 'classnames';
import FormControlLabel from '@material-ui/core/FormControlLabel';
import Typography from '@material-ui/core/Typography';
import InputFieldSearch from '../InputField/InputFieldSearch';
import PanelItem from '../Panels/PanelItem';
import TableCustom from '../Table/TableCustom';
import MaterialSwitch from '../MaterialComponents/Switch';
import CustomRow from '../Custom/CustomRow';
import CustomColumn from '../Custom/CustomColumn';
import TABLE_COLUMN_TYPES from '../Table/ColumnTypes';

const onlyCheckedTableColumns = [
  {
    id: 'name',
    numeric: false,
    disablePadding: false,
    label: 'Name',
    type: TABLE_COLUMN_TYPES.string,
  },
];

class MultiselectPanel extends Component {
  onTableSelection = (selected) => {
    const { onSelect } = this.props;
    onSelect(selected);
  };

  render() {
    const {
      active,
      title,
      data,
      tableConfiguration,
      onSearch,
      onPaginate,
      onPaginationLimitChanged,
      onSort,
      currentPage,
      totalRows,
      currentlySelected,
      nameIdPaired,
      indicatorsEditor,
      paginationLimit,
      impacts,
      styles,
      onlySelectedSwitcher = false,
      onlySelectedFilter = false,
      onlySelectedChange = () => { },
    } = this.props;
    let currentlySelectedFromKey = currentlySelected;
    if (nameIdPaired) {
      currentlySelectedFromKey = [];
      for (let i = 0; i < currentlySelected.length; i++) {
        currentlySelectedFromKey.push(
          `${currentlySelected[i].id}:${currentlySelected[i].name}`,
        );
      }
    }
    return (
      <PanelItem
        panelItemType="small"
        panelItemShow={active ? 'panel-item-show' : ''}
      >
        <div
          className={classnames(styles['panel-item-inner'])}
          style={{ padding: '5px' }}
        >
          <h3
            className={classnames(styles['panel-item-title'])}
            style={{ marginBottom: '5px' }}
          >
            {title}
          </h3>
          <CustomRow
            style={{
              maxWidth: '100%',
              margin: '0px',
            }}
          >
            <CustomColumn
              customColumn={
                indicatorsEditor || onlySelectedSwitcher ? 'md-6' : 'md-12'
              }
              style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center'
              }}
            >
              <InputFieldSearch
                style={{
                  width: '100%',
                  boxSizing: 'border-box',
                }}
                onChange={onSearch}
              />
            </CustomColumn>
            {indicatorsEditor || onlySelectedSwitcher ? (
              <CustomColumn customColumn="md-6">
                <FormControlLabel
                  labelPlacement="top"
                  control={
                    <MaterialSwitch
                      size="small"
                      value={onlySelectedFilter}
                      checked={onlySelectedFilter}
                      onChange={onlySelectedChange}
                    />
                  }
                  label={
                    <Typography style={{
                      fontSize: '13px',
                    }}
                    >
                      Selected items only
                    </Typography>
                  }
                />
              </CustomColumn>
            ) : null}
          </CustomRow>
          <TableCustom
            style={{ minWidth: 'auto' }}
            columnConfiguration={
              onlySelectedFilter
                ? indicatorsEditor
                  ? tableConfiguration
                  : onlyCheckedTableColumns
                : tableConfiguration
            }
            tableData={onlySelectedFilter ? currentlySelected : data}
            onTableSelectionChanged={this.onTableSelection}
            onPaginate={onPaginate}
            onSort={onSort}
            onPaginationLimitChanged={onPaginationLimitChanged}
            limit={paginationLimit}
            currentPage={currentPage}
            totalRows={totalRows}
            nameIdPaired={nameIdPaired}
            indicatorsEditor={indicatorsEditor}
            checkable
            selected={currentlySelectedFromKey}
            impacts={impacts}
            paginated={!onlySelectedFilter}
            enabledColumn="activate"
          />
        </div>
      </PanelItem>
    );
  }
}

export default MultiselectPanel;
