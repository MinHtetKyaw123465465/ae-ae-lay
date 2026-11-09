import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles.css';
import './theme.css';
import {initPwa} from './pwa';
initPwa();
createRoot(document.getElementById('root')!).render(<React.StrictMode><App/></React.StrictMode>);
