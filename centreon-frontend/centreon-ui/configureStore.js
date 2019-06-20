import { createStore, applyMiddleware } from 'redux'
import reducers from './reducers';
import { composeWithDevTools } from 'redux-devtools-extension'
import thunk from "redux-thunk";
import createSagaMiddleware from "redux-saga";
import sagas from "./sagas";

const sagaMiddleware = createSagaMiddleware();

const store = createStore(reducers, composeWithDevTools(
  applyMiddleware(...[thunk, sagaMiddleware]),
))

sagaMiddleware.run(sagas);

export default store;