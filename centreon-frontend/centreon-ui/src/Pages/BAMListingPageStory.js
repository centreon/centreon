/* eslint-disable no-shadow */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React, { Component } from "react";
import BAMListingPage from "./BAMListing";
import Panels from "../Panels";
import imagesMock from "../Mocks/images.json";
import commandsMock from "../Mocks/command.json";
import timeperiodsMock from "../Mocks/timeperiod.json";
import kpiMock from "../Mocks/kpi.json";
import escalationMock from "../Mocks/escalation.json";
import contactGroupsMock from "../Mocks/contactGroups.json";
import businessViewsMock from "../Mocks/businessViews.json";

class BAMListingPageStory extends Component {
  state = {
    panelActive: true,
    activeBA: {},
    currentlySelected: [],
    businessViews: businessViewsMock.result.entities,
    centreonImages: imagesMock.result.entities,
    timeperiods: timeperiodsMock.result.entities,
    contactGroups: contactGroupsMock.result.entities,
    remoteServers: [],
    escalations: escalationMock.result.entities,
    eventHandlerCommands: commandsMock.result.entities,
    kpis: kpiMock.result.entities,
  };

  togglePanel = () => {
    const { panelActive } = this.state;
    this.setState({
      panelActive: !panelActive
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
      kpis
    } = this.state;
    return (
      <React.Fragment>
        <BAMListingPage
          onAddClicked={this.togglePanel.bind(this)}
          onSearch={() => {
            console.log("onSearch clicked");
          }}
          onDelete={(a, b, c) => {
            console.log("onDelete clicked", a, b, c, currentlySelected);
          }}
          onDuplicate={(a, b, c) => {
            console.log("onDuplicate clicked", a, b, c, currentlySelected);
          }}
          onMassiveChange={(a, b, c) => {
            console.log("onMassiveChange clicked", a, b, c, currentlySelected);
          }}
          onEnable={(a, b, c) => {
            console.log("onEnable clicked", a, b, c, currentlySelected);
          }}
          onDisable={(a, b, c) => {
            console.log("onDisable clicked", a, b, c, currentlySelected);
          }}
          onPaginate={() => {
            console.log("onPaginate clicked");
          }}
          onSort={() => {
            console.log("onSort clicked");
          }}
          tableData={BAMTableData.result.entities}
          onTableSelectionChanged={currentlySelected => {
            this.setState({
              currentlySelected
            });
          }}
          currentlySelected={currentlySelected}
          onPaginationLimitChanged={() => {
            console.log("onPaginationLimitChanged");
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
          kpis={kpis}
          contactGroups={contactGroups}
          businessViews={businessViews}
          remoteServers={remoteServers}
        />
      </React.Fragment>
    );
  }
}
export default BAMListingPageStory;
