<?php
/**
 * Admin Dashboard - Visitor Management System
 * 
 * Administrative interface for managing visitors, viewing check-in history,
 * monitoring training compliance, and database maintenance.
 * 
 * Sections (in display order):
 * 1. Statistics Overview - Key metrics at a glance
 * 2. Currently Checked In - Active visitors on-site
 * 3. Recent Visits - Visit history log
 * 4. Training Management - Contractor training tracking
 * 5. Training Alerts - Expiring/expired training notifications
 * 6. Database Information - System stats and maintenance tools
 * 
 * @package    SuterraGuestCheckin
 * @subpackage Admin
 * @author     Pacific Office Automation
 * @version    2.2
 * @since      2024-01-15
 */

require_once 'auth.php';

// Require authentication
requireAuth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Visitor Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9f7;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #5A7654 0%, #6B8668 100%);
            color: white;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .logo {
            height: 60px;
            width: auto;
            filter: brightness(0) invert(1);
        }
        
        .header-text h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            background: linear-gradient(135deg, #5A7654 0%, #6B8668 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(90, 118, 84, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #6B8668 0%, #7C9579 100%);
        }
        
        .btn-success:hover {
            box-shadow: 0 5px 15px rgba(107, 134, 104, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }
        
        .btn-warning:hover {
            box-shadow: 0 5px 15px rgba(243, 156, 18, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }
        
        .db-management-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .db-action-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .db-action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .db-action-card h4 {
            color: #5A7654;
            font-size: 1.3em;
            margin-bottom: 10px;
        }
        
        .db-action-card p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.4;
        }
        
        .db-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9f7;
            border-radius: 8px;
            border-left: 4px solid #5A7654;
        }
        
        .info-label {
            font-weight: 600;
            color: #333;
        }
        
        .info-value {
            font-weight: 700;
            color: #5A7654;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 3em;
            font-weight: 700;
            color: #5A7654;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1.1em;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .section-title {
            font-size: 1.8em;
            margin-bottom: 25px;
            color: #5A7654;
            border-bottom: 3px solid #6B8668;
            padding-bottom: 10px;
        }
        
        .table-container {
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9f7;
            font-weight: 600;
            color: #5A7654;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-checked-in {
            background: #d4edda;
            color: #155724;
        }
        
        .status-checked-out {
            background: #f8d7da;
            color: #721c24;
        }
        
        .duration {
            font-weight: 600;
            color: #5A7654;
        }
        
        .orientation-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            margin-right: 5px;
            display: inline-block;
        }
        
        .orientation-badge.contractor {
            background: #ff9800;
            color: white;
        }
        
        .orientation-badge.visitor {
            background: #2196f3;
            color: white;
        }
        
        .orientation-badge.none {
            background: #e0e0e0;
            color: #666;
        }
        
        .loading {
            text-align: center;
            padding: 50px;
            color: #666;
            font-size: 1.2em;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .training-alerts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .alert-section {
            border-radius: 10px;
            padding: 20px;
            border: 2px solid;
        }
        
        .alert-section.expired {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .alert-section.expiring-soon {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        .alert-section h3 {
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        
        .alert-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .alert-item {
            background: rgba(255,255,255,0.7);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid;
        }
        
        .alert-section.expired .alert-item {
            border-left-color: #dc3545;
        }
        
        .alert-section.expiring-soon .alert-item {
            border-left-color: #ffc107;
        }
        
        .expired-date {
            color: #dc3545;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .expiring-date {
            color: #856404;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .training-section {
            margin-bottom: 40px;
        }
        
        .training-section h3 {
            color: #5A7654;
            margin-bottom: 15px;
            font-size: 1.4em;
            border-bottom: 2px solid #5A7654;
            padding-bottom: 8px;
        }
        
        .training-table-container {
            overflow-x: auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .training-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        .training-table th {
            background: #f8f9fa;
            color: #5A7654;
            font-weight: 600;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #e0e0e0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .training-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
        }
        
        .training-row:hover {
            background: #f8f9fa;
        }
        
        .training-row.expired {
            background: #f8d7da !important;
        }
        
        .training-row.expiring {
            background: #fff3cd !important;
        }
        
        .training-row.current {
            background: #d4edda !important;
        }
        
        .training-row.no-date {
            background: #f8f9fa !important;
        }
        
        .status-badge.training-expired {
            background: #dc3545;
            color: white;
        }
        
        .status-badge.training-expiring {
            background: #ffc107;
            color: #856404;
        }
        
        .status-badge.training-current {
            background: #28a745;
            color: white;
        }
        
        .status-badge.training-no-date {
            background: #6c757d;
            color: white;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.85em;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-edit {
            background: #007bff;
            color: white;
        }
        
        .btn-edit:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        
        .training-date, .expiry-date {
            font-family: monospace;
            font-size: 0.9em;
        }
        
        .suterra-contact {
            color: #5A7654;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .header-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .logo {
                height: 50px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            table {
                font-size: 0.9em;
            }
            
            th, td {
                padding: 10px;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .checkin-section {
                border-right: none;
                border-bottom: 2px solid #f0f0f0;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <img src="../sut-primary-logo.svg" alt="Company Logo" class="logo">
            <div class="header-text">
                <h1>Admin Dashboard</h1>
                <p>Visitor Management System - Overview and Reports</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="../index.php" class="btn">&larr; Back to Check-in</a>
            <button id="export-csv" class="btn btn-success">Export CSV Report</button>
            <button id="backup-db" class="btn btn-success">Backup Database</button>
            <button id="import-training" class="btn btn-warning">Import Training Data</button>
            <button id="refresh-data" class="btn">Refresh Data</button>
            <button id="clear-visits" class="btn btn-warning">Clear Visit History</button>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" id="stat-current">-</div>
                <div class="stat-label">Currently Here</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="stat-today">-</div>
                <div class="stat-label">Visits Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="stat-total">-</div>
                <div class="stat-label">Total Visitors</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="stat-avg-duration">-</div>
                <div class="stat-label">Avg. Visit (min)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="stat-db-size">-</div>
                <div class="stat-label">Database Size (MB)</div>
            </div>
        </div>
        
        <!-- Current Visitors (moved to top) -->
        <div class="section">
            <h2 class="section-title">Currently Checked In</h2>
            <div id="current-visitors-table">
                <div class="loading">Loading current visitors...</div>
            </div>
        </div>
        
        <!-- Recent Visits (moved up) -->
        <div class="section">
            <h2 class="section-title">Recent Visits</h2>
            <div id="recent-visits-table">
                <div class="loading">Loading recent visits...</div>
            </div>
        </div>
        
        <!-- Training Management Section -->
        <div class="section">
            <h2 class="section-title">Training Management</h2>
            <p style="color: #666; margin-bottom: 20px; font-size: 0.95em;">
                Showing visitors with activity in the last 2 years. Training records older than 2 years are archived.
            </p>
            <div id="training-management">
                <div class="loading">Loading training data...</div>
            </div>
        </div>
        
        <!-- Training Alerts Section -->
        <div class="section">
            <h2 class="section-title">Training Alerts</h2>
            <div id="training-alerts">
                <div class="loading">Loading training alerts...</div>
            </div>
        </div>
        
        <!-- Database Info Section (moved to bottom) -->
        <div class="section">
            <h2 class="section-title">Database Information &amp; Management</h2>
            <div class="db-info-grid">
                <div class="info-item">
                    <span class="info-label">Total Records:</span>
                    <span class="info-value" id="total-records">-</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Contractor Orientations:</span>
                    <span class="info-value" id="contractor-orientations">-</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Visitor Orientations:</span>
                    <span class="info-value" id="visitor-orientations">-</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Both Orientations:</span>
                    <span class="info-value" id="both-orientations">-</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Visits (Last 30 Days):</span>
                    <span class="info-value" id="recent-visits">-</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Data Since:</span>
                    <span class="info-value" id="data-since">-</span>
                </div>
            </div>
            
            <div class="db-management-grid">
                <div class="db-action-card">
                    <h4>Export Data</h4>
                    <p>Download visit data as CSV for reporting and analysis. Includes all visitor information and visit history.</p>
                </div>
                <div class="db-action-card">
                    <h4>Backup Database</h4>
                    <p>Download a complete backup of the SQLite database file. Can be restored later if needed.</p>
                </div>
                <div class="db-action-card">
                    <h4>Clear Visit History</h4>
                    <p>Remove all check-in/check-out records while keeping visitor information and orientations intact.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        /**
         * AdminDashboard Class
         * 
         * Handles all admin dashboard functionality including:
         * - Loading and displaying visitor data
         * - Training management and alerts
         * - Database operations (export, backup, clear)
         * - Real-time data refresh
         */
        class AdminDashboard {
            constructor() {
                this.init();
            }
            
            init() {
                this.bindEvents();
                this.loadData();
                
                // Auto-refresh every 60 seconds
                setInterval(() => {
                    this.loadData();
                }, 60000);
            }
            
            bindEvents() {
                document.getElementById('refresh-data').addEventListener('click', () => {
                    this.loadData();
                });
                
                document.getElementById('export-csv').addEventListener('click', () => {
                    this.exportCSV();
                });
                
                document.getElementById('backup-db').addEventListener('click', () => {
                    this.backupDatabase();
                });
                
                document.getElementById('clear-visits').addEventListener('click', () => {
                    this.clearVisits();
                });
                
                document.getElementById('import-training').addEventListener('click', () => {
                    this.showTrainingImport();
                });
            }
            
            async loadData() {
                try {
                    await Promise.all([
                        this.loadCurrentVisitors(),
                        this.loadRecentVisits(),
                        this.loadStatistics(),
                        this.loadDatabaseInfo(),
                        this.loadTrainingManagement(),
                        this.loadTrainingAlerts()
                    ]);
                } catch (error) {
                    console.error('Error loading data:', error);
                }
            }
            
            async loadCurrentVisitors() {
                try {
                    const response = await fetch('../api/current.php');
                    const result = await response.json();
                    
                    if (result.success) {
                        this.displayCurrentVisitors(result.visitors);
                        document.getElementById('stat-current').textContent = result.count;
                    }
                } catch (error) {
                    document.getElementById('current-visitors-table').innerHTML = 
                        '<div class="error">Error loading current visitors</div>';
                }
            }
            
            async loadRecentVisits() {
                try {
                    const response = await fetch('../api/recent.php');
                    const result = await response.json();
                    
                    if (result.success) {
                        this.displayRecentVisits(result.visits);
                    }
                } catch (error) {
                    document.getElementById('recent-visits-table').innerHTML = 
                        '<div class="error">Error loading recent visits</div>';
                }
            }
            
            async loadStatistics() {
                try {
                    const response = await fetch('../api/stats.php');
                    const result = await response.json();
                    
                    if (result.success) {
                        document.getElementById('stat-today').textContent = result.today_visits || 0;
                        document.getElementById('stat-total').textContent = result.total_visitors || 0;
                        document.getElementById('stat-avg-duration').textContent = 
                            result.avg_duration ? Math.round(result.avg_duration) : '-';
                    }
                } catch (error) {
                    console.error('Error loading statistics:', error);
                }
            }
            
            async loadDatabaseInfo() {
                try {
                    const response = await fetch('../api/db-stats.php');
                    const result = await response.json();
                    
                    if (result.success) {
                        document.getElementById('stat-db-size').textContent = result.database_file_size_mb;
                        document.getElementById('total-records').textContent = 
                            `${result.total_visitors} visitors, ${result.total_visits} visits`;
                        document.getElementById('contractor-orientations').textContent = 
                            result.orientation_stats.contractor_completed;
                        document.getElementById('visitor-orientations').textContent = 
                            result.orientation_stats.visitor_completed;
                        document.getElementById('both-orientations').textContent = 
                            result.orientation_stats.both_completed;
                        document.getElementById('recent-visits').textContent = 
                            result.recent_activity.visits_last_30_days;
                        document.getElementById('data-since').textContent = 
                            result.date_ranges.first_visitor ? new Date(result.date_ranges.first_visitor).toLocaleDateString() : 'No data';
                    }
                } catch (error) {
                    console.error('Error loading database info:', error);
                }
            }
            
            displayCurrentVisitors(visitors) {
                const container = document.getElementById('current-visitors-table');
                
                if (visitors.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No visitors currently checked in</p>';
                    return;
                }
                
                const tableHTML = `
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Company</th>
                                    <th>Visiting</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Badge #</th>
                                    <th>Check-in Time</th>
                                    <th>Duration</th>
                                    <th>Orientations</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${visitors.map(visitor => `
                                    <tr>
                                        <td>${this.escapeHtml(visitor.name)}</td>
                                        <td>${this.escapeHtml(visitor.company)}</td>
                                        <td class="suterra-contact">${visitor.suterra_contact ? this.escapeHtml(visitor.suterra_contact) : '-'}</td>
                                        <td>${this.escapeHtml(visitor.email)}</td>
                                        <td>${this.escapeHtml(visitor.phone)}</td>
                                        <td>${visitor.badge_number ? this.escapeHtml(visitor.badge_number) : '-'}</td>
                                        <td>${this.formatDateTime(visitor.check_in_time)}</td>
                                        <td class="duration">${this.calculateDuration(visitor.check_in_time)}</td>
                                        <td>${this.formatOrientations(visitor)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
                
                container.innerHTML = tableHTML;
            }
            
            displayRecentVisits(visits) {
                const container = document.getElementById('recent-visits-table');
                
                if (visits.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No visits found</p>';
                    return;
                }
                
                const tableHTML = `
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Company</th>
                                    <th>Visiting</th>
                                    <th>Email</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Orientations</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${visits.map(visit => `
                                    <tr>
                                        <td>${this.escapeHtml(visit.name)}</td>
                                        <td>${this.escapeHtml(visit.company)}</td>
                                        <td class="suterra-contact">${visit.suterra_contact ? this.escapeHtml(visit.suterra_contact) : '-'}</td>
                                        <td>${this.escapeHtml(visit.email)}</td>
                                        <td>${this.formatDateTime(visit.check_in_time)}</td>
                                        <td>${visit.check_out_time ? this.formatDateTime(visit.check_out_time) : '-'}</td>
                                        <td class="duration">${visit.duration_minutes ? Math.round(visit.duration_minutes) + ' min' : '-'}</td>
                                        <td>
                                            <span class="status-badge ${visit.check_out_time ? 'status-checked-out' : 'status-checked-in'}">
                                                ${visit.check_out_time ? 'Checked Out' : 'Checked In'}
                                            </span>
                                        </td>
                                        <td>${this.formatOrientationsFromVisit(visit)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
                
                container.innerHTML = tableHTML;
            }
            
            async exportCSV() {
                try {
                    const response = await fetch('../api/export.php');
                    const blob = await response.blob();
                    
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `visitor_report_${new Date().toISOString().split('T')[0]}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                } catch (error) {
                    alert('Error exporting data: ' + error.message);
                }
            }
            
            async backupDatabase() {
                if (!confirm('Download a backup of the complete database? This file contains all visitor and visit data.')) {
                    return;
                }
                
                try {
                    const response = await fetch('../api/backup.php');
                    if (!response.ok) {
                        throw new Error('Backup failed');
                    }
                    
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `visitor_database_backup_${new Date().toISOString().split('T')[0]}.db`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    alert('Database backup downloaded successfully!');
                } catch (error) {
                    alert('Error creating backup: ' + error.message);
                }
            }
            
            async clearVisits() {
                const confirmText = 'Are you sure you want to clear all visit history? This will remove all check-in/check-out records but keep visitor information. This action cannot be undone.';
                
                if (!confirm(confirmText)) {
                    return;
                }
                
                if (!confirm('This is your final warning. All visit history will be permanently deleted. Continue?')) {
                    return;
                }
                
                try {
                    const response = await fetch('../api/clear-visits.php', {
                        method: 'POST'
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert(`Successfully cleared ${result.deleted_count} visit records.`);
                        this.loadData();
                    } else {
                        alert('Error clearing visits: ' + result.error);
                    }
                } catch (error) {
                    alert('Error clearing visits: ' + error.message);
                }
            }
            
            async loadTrainingManagement() {
                try {
                    const response = await fetch('../api/training-management.php');
                    const result = await response.json();
                    
                    if (result.success) {
                        this.displayTrainingManagement(result);
                    }
                } catch (error) {
                    document.getElementById('training-management').innerHTML = 
                        '<div class="error">Error loading training management data</div>';
                }
            }
            
            displayTrainingManagement(data) {
                const container = document.getElementById('training-management');
                
                if (data.contractor_count === 0 && data.visitor_count === 0) {
                    container.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No visitors with training data found in the last 2 years</p>';
                    return;
                }
                
                let html = '';
                
                // Contractors section (sorted by most recent activity)
                if (data.contractors.length > 0) {
                    html += `
                        <div class="training-section">
                            <h3>Contractors (${data.contractors.length})</h3>
                            <div class="training-table-container">
                                <table class="training-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Company</th>
                                            <th>Email</th>
                                            <th>Last Training</th>
                                            <th>Expires</th>
                                            <th>Status</th>
                                            <th>Total Visits</th>
                                            <th>Last Visit</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;
                    
                    data.contractors.forEach(contractor => {
                        const statusClass = this.getTrainingStatusClass(contractor.training_status);
                        const statusText = this.getTrainingStatusText(contractor.training_status);
                        const lastTraining = contractor.last_training_date ? 
                            new Date(contractor.last_training_date).toLocaleDateString() : 'Not set';
                        const expires = contractor.training_expires_date ? 
                            new Date(contractor.training_expires_date).toLocaleDateString() : 'Not set';
                        const lastVisit = contractor.last_visit ? 
                            new Date(contractor.last_visit).toLocaleDateString() : 'Never';
                        
                        html += `
                            <tr class="training-row ${statusClass}">
                                <td><strong>${this.escapeHtml(contractor.name)}</strong></td>
                                <td>${this.escapeHtml(contractor.company)}</td>
                                <td>${this.escapeHtml(contractor.email)}</td>
                                <td class="training-date">${lastTraining}</td>
                                <td class="expiry-date">${expires}</td>
                                <td><span class="status-badge training-${statusClass}">${statusText}</span></td>
                                <td>${contractor.total_visits}</td>
                                <td>${lastVisit}</td>
                                <td>
                                    <button class="btn-small btn-edit" onclick="adminDashboard.editTrainingDate(${contractor.id}, '${this.escapeHtml(contractor.name)}', '${contractor.last_training_date || ''}')">
                                        Edit Date
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table></div></div>';
                }
                
                // Visitors section (sorted by most recent visit)
                if (data.visitors.length > 0) {
                    html += `
                        <div class="training-section">
                            <h3>Visitors (${data.visitors.length})</h3>
                            <p style="color: #666; margin-bottom: 15px; font-size: 0.9em;">
                                Visitors completed orientation but don't require annual retraining.
                            </p>
                            <div class="training-table-container">
                                <table class="training-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Company</th>
                                            <th>Email</th>
                                            <th>Total Visits</th>
                                            <th>Last Visit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;
                    
                    data.visitors.forEach(visitor => {
                        const lastVisit = visitor.last_visit ? 
                            new Date(visitor.last_visit).toLocaleDateString() : 'Never';
                        
                        html += `
                            <tr class="training-row">
                                <td><strong>${this.escapeHtml(visitor.name)}</strong></td>
                                <td>${this.escapeHtml(visitor.company)}</td>
                                <td>${this.escapeHtml(visitor.email)}</td>
                                <td>${visitor.total_visits}</td>
                                <td>${lastVisit}</td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table></div></div>';
                }
                
                container.innerHTML = html;
            }
            
            getTrainingStatusClass(status) {
                switch (status) {
                    case 'expired': return 'expired';
                    case 'expiring_soon': return 'expiring';
                    case 'current': return 'current';
                    default: return 'no-date';
                }
            }
            
            getTrainingStatusText(status) {
                switch (status) {
                    case 'expired': return 'EXPIRED';
                    case 'expiring_soon': return 'Expiring Soon';
                    case 'current': return 'Current';
                    default: return 'No Date Set';
                }
            }
            
            editTrainingDate(visitorId, visitorName, currentDate) {
                const modal = document.createElement('div');
                modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;z-index:1000;';
                
                const container = document.createElement('div');
                container.style.cssText = 'background:white;padding:30px;border-radius:15px;max-width:500px;width:90%;';
                
                const formattedDate = currentDate ? currentDate : '';
                
                container.innerHTML = `
                    <h2 style="margin-bottom:20px;color:#333;">Edit Training Date</h2>
                    <p style="margin-bottom:20px;color:#666;">
                        Update the last completed training date for <strong>${this.escapeHtml(visitorName)}</strong>
                    </p>
                    
                    <form id="edit-training-form">
                        <div style="margin-bottom:20px;">
                            <label for="training-date" style="display:block;margin-bottom:8px;font-weight:600;">
                                Last Training Completion Date:
                            </label>
                            <input type="date" id="training-date" name="training_date" value="${formattedDate}" required 
                                   style="width:100%;padding:12px;border:2px solid #ddd;border-radius:8px;font-size:1em;">
                            <small style="color:#666;display:block;margin-top:5px;">
                                Training will automatically expire 1 year from this date.
                            </small>
                        </div>
                        
                        <div style="display:flex;gap:10px;justify-content:flex-end;">
                            <button type="button" onclick="document.body.removeChild(this.closest('[style*=\\"position:fixed\\"]'))" 
                                    style="padding:10px 20px;background:#6c757d;color:white;border:none;border-radius:5px;cursor:pointer;">
                                Cancel
                            </button>
                            <button type="submit" 
                                    style="padding:10px 20px;background:#28a745;color:white;border:none;border-radius:5px;cursor:pointer;">
                                Update Training Date
                            </button>
                        </div>
                    </form>
                `;
                
                modal.appendChild(container);
                document.body.appendChild(modal);
                
                document.getElementById('edit-training-form').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const trainingDate = document.getElementById('training-date').value;
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'update_single');
                        formData.append('visitor_id', visitorId);
                        formData.append('training_date', trainingDate);
                        
                        const response = await fetch('../api/training-import.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert(`Training date updated successfully for ${visitorName}`);
                            document.body.removeChild(modal);
                            this.loadData();
                        } else {
                            alert('Update failed: ' + result.error);
                        }
                    } catch (error) {
                        alert('Update error: ' + error.message);
                    }
                });
            }
            
            async loadTrainingAlerts() {
                try {
                    const response = await fetch('../api/training-alerts.php');
                    const result = await response.json();
                    
                    if (result.success) {
                        this.displayTrainingAlerts(result);
                    }
                } catch (error) {
                    document.getElementById('training-alerts').innerHTML = 
                        '<div class="error">Error loading training alerts</div>';
                }
            }
            
            displayTrainingAlerts(data) {
                const container = document.getElementById('training-alerts');
                
                if (data.expired_count === 0 && data.expiring_soon_count === 0) {
                    container.innerHTML = `
                        <div style="text-align: center; color: #28a745; padding: 20px;">
                            <h3>All Training Up to Date</h3>
                            <p>${data.tracked_contractors} of ${data.total_contractors} contractors have training dates tracked.</p>
                        </div>
                    `;
                    return;
                }
                
                let html = '<div class="training-alerts-grid">';
                
                if (data.expired_count > 0) {
                    html += `
                        <div class="alert-section expired">
                            <h3>Expired Training (${data.expired_count})</h3>
                            <div class="alert-list">
                    `;
                    
                    data.expired.forEach(contractor => {
                        html += `
                            <div class="alert-item">
                                <strong>${this.escapeHtml(contractor.name)}</strong><br>
                                <small>${this.escapeHtml(contractor.company)} - ${this.escapeHtml(contractor.email)}</small><br>
                                <span class="expired-date">Expired: ${new Date(contractor.training_expires_date).toLocaleDateString()}</span>
                            </div>
                        `;
                    });
                    
                    html += '</div></div>';
                }
                
                if (data.expiring_soon_count > 0) {
                    html += `
                        <div class="alert-section expiring-soon">
                            <h3>Expiring Soon (${data.expiring_soon_count})</h3>
                            <div class="alert-list">
                    `;
                    
                    data.expiring_soon.forEach(contractor => {
                        html += `
                            <div class="alert-item">
                                <strong>${this.escapeHtml(contractor.name)}</strong><br>
                                <small>${this.escapeHtml(contractor.company)} - ${this.escapeHtml(contractor.email)}</small><br>
                                <span class="expiring-date">Expires: ${new Date(contractor.training_expires_date).toLocaleDateString()}</span>
                            </div>
                        `;
                    });
                    
                    html += '</div></div>';
                }
                
                html += '</div>';
                
                html += `
                    <div style="text-align: center; margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <small>Tracking ${data.tracked_contractors} of ${data.total_contractors} contractors. 
                        <a href="#" onclick="adminDashboard.showTrainingImport()" style="color: #007bff;">Import training data</a> to track more.</small>
                    </div>
                `;
                
                container.innerHTML = html;
            }
            
            showTrainingImport() {
                const modal = document.createElement('div');
                modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;z-index:1000;';
                
                const container = document.createElement('div');
                container.style.cssText = 'background:white;padding:30px;border-radius:15px;max-width:600px;width:90%;';
                
                container.innerHTML = `
                    <h2 style="margin-bottom:20px;color:#333;">Import Training Data</h2>
                    <p style="margin-bottom:20px;color:#666;">
                        Upload a CSV file with contractor training data. The CSV should have these columns:<br>
                        <strong>Name, Email, Phone, Company, Training Date</strong>
                    </p>
                    
                    <div style="background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:20px;font-size:0.9em;">
                        <strong>Example CSV format:</strong><br>
                        John Smith, john@company.com, 555-1234, ABC Corp, 2024-01-15<br>
                        Jane Doe, jane@company.com, 555-5678, XYZ Inc, 2024-02-20
                    </div>
                    
                    <form id="training-import-form" enctype="multipart/form-data">
                        <div style="margin-bottom:20px;">
                            <label for="training-csv" style="display:block;margin-bottom:8px;font-weight:600;">CSV File:</label>
                            <input type="file" id="training-csv" name="training_csv" accept=".csv" required 
                                   style="width:100%;padding:10px;border:2px solid #ddd;border-radius:8px;">
                        </div>
                        
                        <div style="display:flex;gap:10px;justify-content:flex-end;">
                            <button type="button" onclick="document.body.removeChild(this.closest('[style*=\\"position:fixed\\"]'))" 
                                    style="padding:10px 20px;background:#6c757d;color:white;border:none;border-radius:5px;cursor:pointer;">
                                Cancel
                            </button>
                            <button type="submit" 
                                    style="padding:10px 20px;background:#28a745;color:white;border:none;border-radius:5px;cursor:pointer;">
                                Import Training Data
                            </button>
                        </div>
                    </form>
                `;
                
                modal.appendChild(container);
                document.body.appendChild(modal);
                
                document.getElementById('training-import-form').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const formData = new FormData();
                    formData.append('training_csv', document.getElementById('training-csv').files[0]);
                    
                    try {
                        const response = await fetch('../api/training-import.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert(`Success! ${result.imported_count} training records imported.`);
                            if (result.error_count > 0) {
                                console.log('Import errors:', result.errors);
                                alert(`Note: ${result.error_count} records had errors. Check console for details.`);
                            }
                            document.body.removeChild(modal);
                            this.loadData();
                        } else {
                            alert('Import failed: ' + result.error);
                        }
                    } catch (error) {
                        alert('Import error: ' + error.message);
                    }
                });
            }
            
            formatDateTime(dateString) {
                const date = new Date(dateString);
                return date.toLocaleString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
            }
            
            calculateDuration(checkInTime) {
                try {
                    const now = new Date();
                    let checkIn;
                    
                    if (checkInTime.includes('T')) {
                        checkIn = new Date(checkInTime);
                    } else {
                        checkIn = new Date(checkInTime.replace(' ', 'T') + 'Z');
                        checkIn = new Date(checkIn.getTime() - checkIn.getTimezoneOffset() * 60000);
                    }
                    
                    if (isNaN(checkIn.getTime())) {
                        return 'Invalid time';
                    }
                    
                    const diffMs = now - checkIn;
                    
                    if (diffMs < 0) {
                        checkIn = new Date(checkInTime.replace(' ', 'T'));
                        const newDiffMs = now - checkIn;
                        if (newDiffMs < 0) {
                            return 'Time sync issue';
                        }
                        const diffMinutes = Math.floor(newDiffMs / (1000 * 60));
                        return this.formatDuration(diffMinutes);
                    }
                    
                    const diffMinutes = Math.floor(diffMs / (1000 * 60));
                    return this.formatDuration(diffMinutes);
                } catch (error) {
                    console.error('Duration calculation error:', error, 'for time:', checkInTime);
                    return 'Calc error';
                }
            }
            
            formatDuration(minutes) {
                if (minutes < 0) return 'Time error';
                if (minutes < 60) {
                    return `${minutes} min`;
                } else {
                    const hours = Math.floor(minutes / 60);
                    const remainingMinutes = minutes % 60;
                    return `${hours}h ${remainingMinutes}m`;
                }
            }
            
            formatOrientations(visitor) {
                const orientations = [];
                if (visitor.contractor_orientation_completed) {
                    orientations.push('<span class="orientation-badge contractor">Contractor</span>');
                }
                if (visitor.visitor_orientation_completed) {
                    orientations.push('<span class="orientation-badge visitor">Visitor</span>');
                }
                return orientations.length > 0 ? orientations.join(' ') : '<span class="orientation-badge none">None</span>';
            }
            
            formatOrientationsFromVisit(visit) {
                const orientations = [];
                if (visit.contractor_orientation_completed == 1) {
                    orientations.push('<span class="orientation-badge contractor">Contractor</span>');
                }
                if (visit.visitor_orientation_completed == 1) {
                    orientations.push('<span class="orientation-badge visitor">Visitor</span>');
                }
                return orientations.length > 0 ? orientations.join(' ') : '<span class="orientation-badge none">None</span>';
            }
            
            escapeHtml(text) {
                if (!text) return '';
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.toString().replace(/[&<>"']/g, (m) => map[m]);
            }
        }
        
        // Initialize dashboard when page loads
        let adminDashboard;
        document.addEventListener('DOMContentLoaded', () => {
            adminDashboard = new AdminDashboard();
        });
    </script>
</body>
</html>
