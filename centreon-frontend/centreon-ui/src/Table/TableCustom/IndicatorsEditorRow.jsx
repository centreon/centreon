/* eslint-disable no-nested-ternary */
/* eslint-disable react/prop-types */

import React, { Component } from 'react';
import TableCell from '@material-ui/core/TableCell';
import InputFieldSelectTableCell from '../../InputField/InputFieldSelectTableCell';
import InputFieldTableCell from '../../InputField/InputFieldTableCell';

class IndicatorsEditorRow extends Component {
  state = {
    type: 'word',
    warning: 1,
    critical: 1,
    unknown: 1,
  };

  componentDidMount() {
    const { row } = this.props;

    if (row.impact.type) {
      this.setState({
        ...row.impact,
      });
    }
  }

  editImpact = () => {
    const { onImpactEdit, row } = this.props;
    onImpactEdit({ ...row, impact: { ...this.state } });
  };

  onImpactChanged = (value, key) => {
    this.setState(
      {
        [key]: value,
      },
      () => this.editImpact(),
    );
  };

  onImpactInputChanged = (event, key) => {
    const { value } = event.target;

    this.onImpactChanged(value, key);
  };

  changeMode = (value) => {
    this.setState(
      {
        type: value,
      },
      () => this.editImpact(),
    );
  };

  render() {
    const { impacts, selected } = this.props;
    const { type, unknown, warning, critical } = this.state;

    return !selected ? null : (
      <React.Fragment>
        <TableCell
          align="left"
          style={{
            padding: '3px 4px',
          }}
        >
          <InputFieldSelectTableCell
            options={[
              {
                id: 'value',
                name: 'Value',
              },
              { id: 'word', name: 'Words' },
            ]}
            active="active"
            size="extrasmall"
            disabled={!selected}
            value={type}
            onChange={this.changeMode}
          />
        </TableCell>
        {type === 'word' ? (
          <React.Fragment>
            <TableCell
              align="left"
              style={{
                padding: '3px 4px',
              }}
            >
              {type !== 'B' ? (
                <InputFieldSelectTableCell
                  options={impacts}
                  value={warning}
                  isColored
                  size="extrasmall"
                  active="active"
                  disabled={!selected}
                  onChange={(value) => {
                    this.onImpactChanged(value, 'warning');
                  }}
                />
              ) : null}
            </TableCell>
            <TableCell
              align="left"
              style={{
                padding: '3px 4px',
              }}
            >
              <InputFieldSelectTableCell
                options={impacts}
                value={critical}
                isColored
                size="extrasmall"
                active="active"
                disabled={!selected}
                onChange={(value) => {
                  this.onImpactChanged(value, 'critical');
                }}
              />
            </TableCell>
            <TableCell
              align="left"
              style={{
                padding: '3px 4px',
              }}
            >
              {type !== 'B' ? (
                <InputFieldSelectTableCell
                  options={impacts}
                  value={unknown}
                  isColored
                  size="extrasmall"
                  active="active"
                  disabled={!selected}
                  onChange={(value) => {
                    this.onImpactChanged(value, 'unknown');
                  }}
                />
              ) : null}
            </TableCell>
          </React.Fragment>
        ) : (
          <React.Fragment>
            <TableCell
              align="left"
              style={{
                padding: '3px 4px',
              }}
            >
              <InputFieldTableCell
                value={warning}
                inputSize="extrasmall"
                disabled={!selected}
                onChange={(event) => {
                  this.onImpactInputChanged(event, 'warning');
                }}
              />
            </TableCell>
            <TableCell
              align="left"
              style={{
                padding: '3px 4px',
              }}
            >
              <InputFieldTableCell
                value={critical}
                inputSize="extrasmall"
                disabled={!selected}
                onChange={(event) => {
                  this.onImpactInputChanged(event, 'critical');
                }}
              />
            </TableCell>
            <TableCell
              align="left"
              style={{
                padding: '3px 4px',
              }}
            >
              <InputFieldTableCell
                value={unknown}
                inputSize="extrasmall"
                disabled={!selected}
                onChange={(event) => {
                  this.onImpactInputChanged(event, 'unknown');
                }}
              />
            </TableCell>
          </React.Fragment>
        )}
      </React.Fragment>
    );
  }
}

export default IndicatorsEditorRow;
