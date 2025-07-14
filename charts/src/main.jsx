import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import PiechartUsersCPU from './PiechartUsersCPU'
import PiechartUsersMemory from './PiechartUsersMemory'

createRoot(document.getElementById('PiechartUsersCPU')).render(
  <StrictMode>
    <PiechartUsersCPU />
  </StrictMode>,
)

createRoot(document.getElementById('PiechartUsersMemory')).render(
  <StrictMode>
    <PiechartUsersMemory />
  </StrictMode>,
)

createRoot(document.getElementById('LinechartLoadavg')).render(
  <StrictMode>
    <LinechartLoadavg />
  </StrictMode>,
)