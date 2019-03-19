import React from 'react'
import { Provider } from 'react-redux'

const ProviderWrapper = ({ children, store }) => (
  <Provider store={store}>
      { children }
  </Provider>
)

export default ProviderWrapper