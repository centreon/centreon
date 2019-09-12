/* eslint-disable no-shadow */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import BAMListingPage from './BAMListing';
import Panels from '../Panels';
import imagesMock from '../Mocks/images';
import commandsMock from '../Mocks/command';
import timeperiodsMock from '../Mocks/timeperiod';
import kpiMock from '../Mocks/kpi';
import escalationMock from '../Mocks/escalation';
import contactGroupsMock from '../Mocks/contactGroups';
import businessViewsMock from '../Mocks/businessViews';
import impactsMock from '../Mocks/impacts';

class BAMListingPageStory extends Component {
  state = {
    panelActive: false,
    activeBA: {},
    currentlySelected: [],
    businessViews: businessViewsMock.result.entities,
    centreonImages: imagesMock.result.entities,
    timeperiods: timeperiodsMock.result.entities,
    contactGroups: contactGroupsMock.result.entities,
    remoteServers: [], 
    escalations: escalationMock.result.entities,
    eventHandlerCommands: commandsMock.result.entities,
    kpis: kpiMock.result,
    impacts: impactsMock.result,
    onlySelectedFilter: false,
  };

  togglePanel = () => {
    const { panelActive } = this.state;
    this.setState({
      panelActive: !panelActive,
    });
  };

  render() {
    const { BAMTableData } = this.props;
    const {
      panelActive,
      currentlySelected,
      activeBA,
      businessViews,
      centreonImages,
      timeperiods,
      contactGroups,
      remoteServers,
      escalations,
      eventHandlerCommands,
      kpis,
      impacts,
      onlySelectedFilter,
    } = this.state;
    return (
      <React.Fragment>
        <BAMListingPage
          onAddClicked={this.togglePanel.bind(this)}
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
            BAMTableData.result.pagination.offset !== 0
              ? BAMTableData.result.pagination.offset /
                BAMTableData.result.pagination.limit
              : 0
          }
          totalRows={BAMTableData.result.pagination.total}
        />
        <Panels
          active={panelActive}
          panelData={activeBA}
          centreonImages={centreonImages}
          eventHandlerCommands={eventHandlerCommands}
          escalations={escalations}
          timeperiods={timeperiods}
          timeperiodsForSelect={timeperiods}
          kpis={kpis}
          contactGroups={contactGroups}
          businessViews={businessViews}
          remoteServers={remoteServers}
          impacts={impacts.entities}
          multiSelectFilters={{
            timeperiods: {
              limit: 30,
              offset: 0,
              searchString: '',
              sortf: false,
              sorto: false,
            },
            timeperiodsForSelect: {
              limit: 500,
              offset: 0,
              searchString: '',
              sortf: false,
              sorto: false,
            },
            kpis: {
              limit: 30,
              offset: 0,
              searchString: '',
              sortf: false,
              sorto: false,
            },
            businessViews: {
              limit: 30,
              offset: 0,
              searchString: '',
              sortf: false,
              sorto: false,
            },
            contactGroups: {
              limit: 30,
              offset: 0,
              searchString: '',
              sortf: false,
              sorto: false,
            },
            escalations: {
              limit: 30,
              offset: 0,
              searchString: '',
              sortf: false,
              sorto: false,
            },
          }}
          onClose={() => {
            this.setState({
              panelActive: !panelActive,
            });
          }}
          onlySelectedFilter={onlySelectedFilter}
          onlySelectedChange={() => {
            this.setState({
              onlySelectedFilter: !onlySelectedFilter,
            });
          }}
        />
      </React.Fragment>
    );
  }
}
export default BAMListingPageStory;
