import React, { Component } from 'react';
import BAMListingPage from './BAMListing';

class BAMListingPageStory extends Component {
  state = {
    panelActive: false,
    currentlySelected: [],
  };

  togglePanel = () => {
    const { panelActive } = this.state;
    this.setState({
      panelActive: !panelActive,
    });
  };

  render() {
    const { BAMTableData } = this.props;
    const { panelActive, currentlySelected } = this.state;
    return (
      <BAMListingPage
        onAddClicked={() => {
          this.togglePanel();
        }}
        onSearch={() => {
          console.log('onSearch clicked');
        }}
        onDelete={(a, b, c) => {
          console.log('onDelete clicked', a, b, c, currentlySelected);
        }}
        onDuplicate={(a, b, c) => {
          console.log('onDuplicate clicked', a, b, c, currentlySelected);
        }}
        onMassiveChange={(a, b, c) => {
          console.log('onMassiveChange clicked', a, b, c, currentlySelected);
        }}
        onEnable={(a, b, c) => {
          console.log('onEnable clicked', a, b, c, currentlySelected);
        }}
        onDisable={(a, b, c) => {
          console.log('onDisable clicked', a, b, c, currentlySelected);
        }}
        onPaginate={() => {
          console.log('onPaginate clicked');
        }}
        onSort={() => {
          console.log('onSort clicked');
        }}
        tableData={BAMTableData.result.entities}
        onTableSelectionChanged={(currentlySelected) => {
          this.setState({
            currentlySelected,
          });
        }}
        currentlySelected={currentlySelected}
        onPaginationLimitChanged={() => {
          console.log('onPaginationLimitChanged');
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
