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
  IconInsertChart,
  Panels
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
      onTableSelectionChanged,
      onPaginationLimitChanged,
      paginationLimit,
      totalRows,
      currentPage,
      panelActive
    } = this.props;
    return (
      <React.Fragment>
        <Breadcrumb breadcrumbs={breadcrumbs} />
        <Divider />
        <Paper elevation={0} style={{ overflow: "hidden" }}>
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
          <CustomRow additionalStyles={["center-vertical"]}>
            <CustomColumn
              customColumn="md-4"
              additionalStyles={["flex-none", "container__col-xs-12", "m-0"]}
            >
              <ButtonCustom label="ADD" onClick={onAddClicked} />
            </CustomColumn>
            <CustomColumn
              customColumn="md-3"
              additionalStyles={["flex-none", "container__col-xs-12", "m-0"]}
            >
              <IconDelete label="Delete" onClick={onDelete} />
            </CustomColumn>
            <CustomColumn
              customColumn="md-3"
              additionalStyles={["flex-none", "container__col-xs-12", "m-0"]}
            >
              <IconLibraryAdd label="Duplicate" onClick={onDuplicate} />
            </CustomColumn>
            <CustomColumn
              customColumn="md-3"
              additionalStyles={["flex-none", "container__col-xs-12", "m-0"]}
            >
              <IconInsertChart
                label="Massive change"
                onClick={onMassiveChange}
              />
            </CustomColumn>
            <CustomColumn
              customColumn="md-3"
              additionalStyles={["flex-none", "container__col-xs-12", "m-0"]}
            >
              <IconPowerSettings
                customStyle={{ backgroundColor: "#009fdf", marginTop: 2 }}
                label="Enable/Disable"
                onClick={onToggle}
              />
            </CustomColumn>
          </CustomRow>
        </Paper>
        <Paper elevation={0} style={{ padding: "8px 16px", paddingTop: 0 }}>
          <TableCustom
            columnConfiguration={tableConfiguration}
            tableData={tableData}
            onTableSelectionChanged={onTableSelectionChanged}
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
        <Panels panelTtype="small" togglePanel={panelActive}/>
      </React.Fragment>
    );
  }
}

export default BAMListingPage;
