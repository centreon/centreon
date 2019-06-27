import React, { Component } from "react";
import {
  CustomRow,
  CustomColumn,
  Breadcrumb,
  Divider,
  InputFieldSearch,
  ButtonCustom,
  TableCustom,
  IconDelete,
  IconLibraryAdd,
  IconPowerSettings,
  IconPowerSettingsDisable,
  IconInsertChart,
  Panels,
  Tooltip
} from "../index";

import TABLE_COLUMN_TYPES from "../Table/ColumnTypes";

import Paper from "@material-ui/core/Paper";

const breadcrumbs = [
  {
    label: "Configuration",
    link: ""
  },
  {
    label: "Business Activity",
    link: ""
  },
  {
    label: "Activities",
    link: ""
  }
];

const tableConfiguration = [
  {
    id: "name",
    numeric: false,
    disablePadding: true,
    label: "Name",
    type: TABLE_COLUMN_TYPES.string
  },
  {
    id: "#",
    numeric: true,
    disablePadding: false,
    label: "",
    type: TABLE_COLUMN_TYPES.hoverActions
  },
  {
    id: "activate",
    numeric: true,
    disablePadding: false,
    label: "Activate",
    type: TABLE_COLUMN_TYPES.toggler
  },
  {
    id: "level_c",
    numeric: true,
    disablePadding: false,
    label: "Calculation method",
    type: TABLE_COLUMN_TYPES.number
  },
  {
    id: "description",
    numeric: true,
    disablePadding: false,
    label: "Description",
    type: TABLE_COLUMN_TYPES.number
  }
];

class BAMListingPage extends Component {
  state = {
    selectedElementsCount:0
  }

  onTableSelection = (selected) => {
    const {onTableSelectionChanged} = this.props;
    this.setState({
      selectedElementsCount:selected.length
    },()=>{
      onTableSelectionChanged(selected)
    })
  }

  render() {
    const {
      onAddClicked,
      onSearch,
      onDelete,
      onDuplicate,
      onMassiveChange,
      onToggle,
      onPaginate,
      onSort,
      tableData,
      onPaginationLimitChanged,
      paginationLimit,
      totalRows,
      currentPage
    } = this.props;
    const {selectedElementsCount} = this.state;
    return (
      <React.Fragment>
        <Breadcrumb breadcrumbs={breadcrumbs} />
        <Divider />
        <Paper elevation={0} style={{ padding: "8px 16px" }}>
          <CustomRow>
            <CustomColumn
              customColumn="md-4"
              additionalStyles={[
                "flex-none",
                "container__col-xs-12",
                "m-0",
                "mr-2"
              ]}
            >
              <InputFieldSearch onChange={onSearch} />
            </CustomColumn>
          </CustomRow>
        </Paper>
        <Divider />
        <Paper elevation={0} style={{ padding: "8px 16px" }}>
          <CustomRow>
            <CustomColumn
              customColumn="md-4"
              additionalStyles={["flex-none", "container__col-xs-12", "m-0"]}
            >
              <ButtonCustom label="ADD" onClick={onAddClicked} />
            </CustomColumn>
            {selectedElementsCount > 0 ? (
              <React.Fragment>
                <CustomColumn
                  customColumn="md-3"
                  additionalStyles={[
                    "flex-none",
                    "container__col-xs-12",
                    "m-0",
                    "pr-09"
                  ]}
                >
                  <Tooltip label="Delete">
                    <IconDelete label="Delete" onClick={onDelete} />
                  </Tooltip>
                </CustomColumn>
                <CustomColumn
                  customColumn="md-3"
                  additionalStyles={[
                    "flex-none",
                    "container__col-xs-12",
                    "m-0",
                    "pr-10",
                    "pl-05"
                  ]}
                >
                  <Tooltip label="Duplicate">
                    <IconLibraryAdd label="Duplicate" onClick={onDuplicate} />
                  </Tooltip>
                </CustomColumn>
                <CustomColumn
                  customColumn="md-3"
                  additionalStyles={[
                    "flex-none",
                    "container__col-xs-12",
                    "m-0",
                    "pr-10",
                    "pl-05"
                  ]}
                >
                  <Tooltip label="Enable">
                    <IconPowerSettings
                      active={true}
                      label="Enable"
                      onClick={onToggle}
                    />
                  </Tooltip>
                </CustomColumn>
                <CustomColumn
                  customColumn="md-3"
                  additionalStyles={[
                    "flex-none",
                    "container__col-xs-12",
                    "m-0",
                    "pl-05",
                    "pr-24",
                    "border-right"
                  ]}
                >
                  <Tooltip label="Disable">
                    <IconPowerSettingsDisable
                      active={true}
                      label="Disable"
                      onClick={onToggle}
                    />
                  </Tooltip>
                </CustomColumn>
                <CustomColumn
                  customColumn="md-3"
                  additionalStyles={[
                    "flex-none",
                    "container__col-xs-12",
                    "m-0",
                    "pl-22"
                  ]}
                >
                  <Tooltip label="Massive change">
                    <IconInsertChart
                      label="Massive change"
                      onClick={onMassiveChange}
                    />
                  </Tooltip>
                </CustomColumn>
              </React.Fragment>
            ) : null}
          </CustomRow>
        </Paper>
        <Paper elevation={0} style={{ padding: "8px 16px", paddingTop: 0 }}>
          <TableCustom
            columnConfiguration={tableConfiguration}
            tableData={tableData}
            onTableSelectionChanged={this.onTableSelection}
            onDelete={onDelete}
            onPaginate={onPaginate}
            onSort={onSort}
            onDuplicate={onDuplicate}
            onPaginationLimitChanged={onPaginationLimitChanged}
            limit={paginationLimit}
            currentPage={currentPage}
            totalRows={totalRows}
            checkable={true}
            onToggle={onToggle}
          />
        </Paper>
      </React.Fragment>
    );
  }
}

export default BAMListingPage;
