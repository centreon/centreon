import React, { Component } from "react";
import classnames from "classnames";
import InputFieldSearch from "../InputField/InputFieldSearch";
import PanelItem from "../Panels/PanelItem";
import TableCustom from "../Table/TableCustom";

class MultiselectPanel extends Component {
  onTableSelection = selected => {
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
      currentlySelectedKey,
      paginationLimit,
      styles
    } = this.props;
    let currentlySelectedFromKey = currentlySelected;
    if(currentlySelectedKey){
      currentlySelectedFromKey = [];
      for(let i = 0; i < currentlySelected.length; i++){
        currentlySelectedFromKey.push(currentlySelected[i][currentlySelectedKey]);
      }
    }
    return (
      <PanelItem
        panelItemType="small"
        panelItemShow={active ? "panel-item-show" : ""}
      >
        <div className={classnames(styles["panel-item-inner"])}>
          <h3 className={classnames(styles["panel-item-title"])}>{title}</h3>
          <InputFieldSearch
            style={{
              width: "100%",
              marginBottom: 15,
              boxSizing: "border-box"
            }}
            onChange={onSearch}
          />
          <TableCustom
            style={{ minWidth: "auto" }}
            columnConfiguration={tableConfiguration}
            tableData={data}
            onTableSelectionChanged={this.onTableSelection}
            onPaginate={onPaginate}
            onSort={onSort}
            onPaginationLimitChanged={onPaginationLimitChanged}
            limit={paginationLimit}
            currentPage={currentPage}
            totalRows={totalRows}
            checkable
            selected={currentlySelectedFromKey}
          />
        </div>
      </PanelItem>
    );
  }
}

export default MultiselectPanel;
