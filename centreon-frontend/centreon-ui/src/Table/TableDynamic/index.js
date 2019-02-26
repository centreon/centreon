import React, {Component} from 'react';
import RadioButton from '../../RadioButton';
import SearchLive from '../../Search/SearchLive';
import Checkbox from '../../Checkbox';
import './table-dynamic.scss';

class TableDynamic extends Component {
  render() {
    return (
      <table class="table-dynamic">
        <thead>
          <tr>
            <th scope="col">
            <div className="container__row">
              <div className="container__col-md-3 center-vertical">
                <Checkbox label="ALL HOSTS" name="all-hosts" />
              </div>
              <div className="container__col-md-6 center-vertical">
                <SearchLive />
              </div>
            </div>
            </th>
            <th scope="col"><SearchLive /></th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <Checkbox
                label="Host 1 lorem ipsum dolor sit amet"
                name="host1"
              />
            </td>
            <td>
              <SearchLive />
            </td>
          </tr>
          <tr>
            <td>
              <Checkbox
                label="Host 1 lorem ipsum dolor sit amet"
                name="host1"
              />
            </td>
            <td>
              <SearchLive />
            </td>
          </tr>
          <tr>
            <td>
              <Checkbox
                label="Host 1 lorem ipsum dolor sit amet"
                name="host1"
              />
            </td>
            <td>
              <SearchLive />
            </td>
          </tr>
          <tr>
            <td>
              <Checkbox
                label="Host 1 lorem ipsum dolor sit amet"
                name="host1"
              />
            </td>
            <td>
              <SearchLive />
            </td>
          </tr>
        </tbody>
      </table>
    );
  }
}

export default TableDynamic;