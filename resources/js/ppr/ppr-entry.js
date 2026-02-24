import React from 'react';
import ReactDOM from 'react-dom';
import axios from 'axios';
import App from './App';

const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (token) axios.defaults.headers.common['X-CSRF-TOKEN'] = token;

axios.interceptors.request.use((config) => {
  const sessionToken = typeof localStorage !== 'undefined' && localStorage.getItem('session_token');
  if (sessionToken) {
    config.headers['X-Session-Token'] = sessionToken;
  }
  return config;
});

const root = document.getElementById('ppr-root');
if (root) {
  ReactDOM.render(
    <React.StrictMode>
      <App />
    </React.StrictMode>,
    root
  );
}
