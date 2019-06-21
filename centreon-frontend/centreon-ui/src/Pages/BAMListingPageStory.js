import React, { Component } from "react";
import BAMListingPage from "./BAMListing";

class BAMListingPageStory extends Component {
  state = {
    panelActive: false
  };
  togglePanel = () => {
    const { panelActive } = this.state;
    this.setState({
      panelActive: !panelActive
    });
  };

  render() {
    const { BAMTableData } = this.props;
    const { panelActive } = this.state;
    return (
      <BAMListingPage
        onAddClicked={() => {
          this.togglePanel();
        }}
        onSearch={() => {
          console.log("onSearch clicked");
        }}
        onDelete={() => {
          console.log("onDelete clicked");
        }}
        onDuplicate={() => {
          console.log("onDuplicate clicked");
        }}
        onMassiveChange={() => {
          console.log("onMassiveChange clicked");
        }}
        onToggle={() => {
          console.log("onToggle clicked");
        }}
        onPaginate={() => {
          console.log("onPaginate clicked");
        }}
        onSort={() => {
          console.log("onSort clicked");
        }}
        tableData={BAMTableData.result.entities}
        onTableSelectionChanged={() => {
          console.log("onTableSelectionChanged");
        }}
        onPaginationLimitChanged={() => {
          console.log("onPaginationLimitChanged");
        }}
        paginationLimit={BAMTableData.result.pagination.limit}
        currentPage={
          BAMTableData.result.pagination.offset != 0
            ? BAMTableData.result.pagination.offset /
              BAMTableData.result.pagination.limit
            : 0
        }
        panelActive={panelActive}
        totalRows={BAMTableData.result.pagination.total}
      />
    );
  }
}
export default BAMListingPageStory;
