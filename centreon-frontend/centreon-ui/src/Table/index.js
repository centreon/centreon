import React, {Component} from 'react';
import IconAction from '../Icon/IconAction';
import './table.scss';

class Table extends Component {
  render() {
    return (
      <table class="table table-striped">
        <thead>
          <tr>
            <th scope="col">Task status</th>
            <th scope="col">Discovery source</th>
            <th scope="col">Start time</th>
            <th scope="col">Duration</th>
            <th scope="col">Discovered items</th>
            {/* TO DO - arrow down */}
            <th>30</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <IconAction iconActionType="clock" />
            </td>
            <td>Mark</td>
            <td>Otto</td>
            <td>@mdo</td>
            <td>@mdo</td>
            <td></td>
          </tr>
          <tr>
            <td>
              <IconAction iconActionType="check" iconColor="green" />
            </td>
            <td>Jacob</td>
            <td>Thornton</td>
            <td>@fat</td>
            <td>Thornton</td>
            <td></td>
          </tr>
          <tr>
            <td>
              <IconAction iconActionType="warning" iconColor="red" />
            </td>
            <td>Larry</td>
            <td>the Bird</td>
            <td>@twitter</td>
            <td>the Bird</td>
            <td></td>
          </tr>
        </tbody>
      </table>
    );
  }
}

export default Table;