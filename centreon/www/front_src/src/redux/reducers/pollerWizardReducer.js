<<<<<<< HEAD
/* eslint-disable default-param-last */
=======
>>>>>>> centreon/dev-21.10.x
import * as actions from '../actions/pollerWizardActions';

const initialState = {};

const pollerWizardReducer = (state = initialState, action) => {
  switch (action.type) {
    case actions.SET_POLLER_WIZARD_DATA:
      return { ...state, ...action.pollerData };
    default:
      return state;
  }
};

export default pollerWizardReducer;
