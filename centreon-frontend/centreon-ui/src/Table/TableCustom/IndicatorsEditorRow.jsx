/* eslint-disable no-nested-ternary */
/* eslint-disable react/prop-types */
import React, { Component } from 'react';
import TableCell from '@material-ui/core/TableCell';
import InputFieldSelectTableCell from '../../InputField/InputFieldSelectTableCell';
import InputFieldTableCell from '../../InputField/InputFieldTableCell';

class IndicatorsEditorRow extends Component {
  onImpactChanged = (value, key, event) => {
    const { onImpactEdit, selected } = this.props;
    const rowObject = selected.obj;
    if (key !== 'type') {
      rowObject.impact.type = rowObject.impact.type
        ? rowObject.impact.type
        : 'word';
    }
    rowObject.impact[key] = value;
    onImpactEdit(event, rowObject, true);
  };

  onImpactInputChanged = (event, key) => {
    const { value } = event.target;
    const { onImpactEdit, selected } = this.props;
    const rowObject = selected.obj;
    rowObject.impact[key] = value;
    onImpactEdit(event, rowObject, true);
  };

  render() {
    const { row, impacts, selected } = this.props;
    const rowMode = selected.bool
      ? selected.obj.impact.type
        ? selected.obj.impact.type
        : 'word'
      : row.impact.type
      ? row.impact.type
      : 'word';
    return !selected.bool ? null : (
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
            disabled={!selected.bool}
            value={rowMode}
            onChange={(value, event) => {
              this.onImpactChanged(value, 'type', event);
            }}
          />
        </TableCell>
        {rowMode === 'word' ? (
          <React.Fragment>
            <TableCell
              align="left"
              style={{
                padding: '3px 4px',
              }}
            >
              {selected.obj.type !== 'B' ? (
                <InputFieldSelectTableCell
                  options={impacts}
                  value={
                    selected.bool
                      ? selected.obj.impact.warning
                        ? selected.obj.impact.warning
                        : 1
                      : row.impact.warning
                      ? row.impact.warning
                      : 1
                  }
                  isColored
                  size="extrasmall"
                  active="active"
                  disabled={!selected.bool}
                  onChange={(value, event) => {
                    this.onImpactChanged(value, 'warning', event);
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
                value={
                  selected.bool
                    ? selected.obj.impact.critical
                      ? selected.obj.impact.critical
                      : 1
                    : row.impact.critical
                    ? row.impact.critical
                    : 1
                }
                isColored
                size="extrasmall"
                active="active"
                disabled={!selected.bool}
                onChange={(value, event) => {
                  this.onImpactChanged(value, 'critical', event);
                }}
              />
            </TableCell>
            <TableCell
              align="left"
              style={{
                padding: '3px 4px',
              }}
            >
              {selected.obj.type !== 'B' ? (
                <InputFieldSelectTableCell
                  options={impacts}
                  value={
                    selected.bool
                      ? selected.obj.impact.unknown
                        ? selected.obj.impact.unknown
                        : 1
                      : row.impact.unknown
                      ? row.impact.unknown
                      : 1
                  }
                  isColored
                  size="extrasmall"
                  active="active"
                  disabled={!selected.bool}
                  onChange={(value, event) => {
                    this.onImpactChanged(value, 'unknown', event);
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
                value={
                  selected.bool
                    ? selected.obj.impact.warning
                    : row.impact.warning
                }
                inputSize="extrasmall"
                disabled={!selected.bool}
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
                value={
                  selected.bool
                    ? selected.obj.impact.critical
                    : row.impact.critical
                }
                inputSize="extrasmall"
                disabled={!selected.bool}
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
                value={
                  selected.bool
                    ? selected.obj.impact.unknown
                    : row.impact.unknown
                }
                inputSize="extrasmall"
                disabled={!selected.bool}
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
