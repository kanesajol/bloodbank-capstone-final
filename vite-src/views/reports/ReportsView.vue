<template>
  <ModulePage
    title="System Reports & Analytics"
    description="Automatically calculated and generated from donor and blood consumption records stored in the system. Real-time metrics update whenever records are added."
  >
    <div class="reports-module">
      <!-- ===================== TOP CONTROLS & REPORT NAV ===================== -->
      <div class="reports-header-bar">
        <div class="report-select-group">
          <label for="reportTypeSelect" class="report-select-label">
            <i class="ti ti-file-analytics"></i>
            <span>Select Report:</span>
          </label>
          <div class="select-wrapper">
            <select
              id="reportTypeSelect"
              v-model="activeTab"
              class="report-dropdown-select"
            >
              <option
                v-for="tab in reportTabs"
                :key="tab.id"
                :value="tab.id"
              >
                {{ tab.label }}
              </option>
            </select>
            <i class="ti ti-chevron-down select-chevron"></i>
          </div>
        </div>

        <div class="header-actions">
          <span class="live-sync-badge">
            <span class="pulse-dot"></span>
            Auto-Generated Live
          </span>
          <button type="button" class="btn-print" @click="printReport">
            <i class="ti ti-printer"></i> Print / Export
          </button>
        </div>
      </div>

      <!-- ==================================================================== -->
      <!-- 1. MONTHLY DONOR REPORT                                              -->
      <!-- ==================================================================== -->
      <section v-if="activeTab === 'monthly'" class="report-section">
        <div class="section-title-wrap">
          <div>
            <h3 class="section-heading">Monthly Donor Report</h3>
            <p class="section-subheading">
              List and statistics of donors who contributed in
              <strong>{{ monthNames[monthlyFilter.month - 1] }} {{ monthlyFilter.year }}</strong>.
            </p>
          </div>

          <!-- Filters -->
          <div class="filters-inline">
            <div class="filter-group">
              <label>Month:</label>
              <select v-model.number="monthlyFilter.month" class="filter-select">
                <option v-for="(m, idx) in monthNames" :key="m" :value="idx + 1">{{ m }}</option>
              </select>
            </div>
            <div class="filter-group">
              <label>Year:</label>
              <select v-model.number="monthlyFilter.year" class="filter-select">
                <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>
        </div>


        <!-- Blood Type Distribution Bar in Month -->
        <div v-if="monthlyStats.bloodTypeBreakdown.length" class="card-box distribution-box">
          <h4 class="box-title">Blood Type Breakdown ({{ monthNames[monthlyFilter.month - 1] }} {{ monthlyFilter.year }})</h4>
          <div class="pill-group">
            <div
              v-for="bt in monthlyStats.bloodTypeBreakdown"
              :key="bt.type"
              class="breakdown-pill"
            >
              <span class="badge-bt">{{ bt.type }}</span>
              <span class="pill-count"><strong>{{ bt.count }}</strong> donation{{ bt.count !== 1 ? 's' : '' }}</span>
            </div>
          </div>
        </div>

        <!-- Monthly Donors List Table -->
        <div class="card-box">
          <div class="box-header">
            <h4 class="box-title">Donor Records List ({{ monthlyDonations.length }})</h4>
            <span class="record-badge">{{ monthNames[monthlyFilter.month - 1] }} {{ monthlyFilter.year }}</span>
          </div>

          <div class="table-wrap">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Donor Name</th>
                  <th>Blood Type</th>
                  <th>Age</th>
                  <th>Gender</th>
                  <th>Location</th>
                  <th>Donation Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="monthlyDonations.length === 0">
                  <td colspan="6" class="empty-state">
                    No donation records found for {{ monthNames[monthlyFilter.month - 1] }} {{ monthlyFilter.year }}.
                  </td>
                </tr>
                <tr v-for="(rec, idx) in monthlyDonations" :key="idx">
                  <td class="font-medium text-dark">{{ rec.donorName }}</td>
                  <td><span class="blood-type-badge">{{ rec.bloodType }}</span></td>
                  <td>{{ rec.age }} yrs</td>
                  <td>
                    <span :class="['gender-tag', rec.gender.toLowerCase()]">{{ rec.gender }}</span>
                  </td>
                  <td>{{ rec.location || rec.barangay }}</td>
                  <td>{{ formatDate(rec.date) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ==================================================================== -->
      <!-- 2. QUARTERLY NEW DONOR REPORT                                        -->
      <!-- ==================================================================== -->
      <section v-else-if="activeTab === 'quarterly'" class="report-section">
        <div class="section-title-wrap">
          <div>
            <h3 class="section-heading">Quarterly New Donor Report</h3>
            <p class="section-subheading">
              Automatic count of newly registered first-time donors categorized by 3-month quarterly periods in
              <strong>{{ quarterlyYear }}</strong>.
            </p>
          </div>

          <div class="filters-inline">
            <div class="filter-group">
              <label>Reporting Year:</label>
              <select v-model.number="quarterlyYear" class="filter-select">
                <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- 4 Quarters Summary Cards -->
        <div class="quarter-grid">
          <div
            v-for="q in quarterlyData"
            :key="q.quarter"
            class="quarter-card"
            :class="{ 'quarter-card--active': selectedQuarter === q.quarter }"
            @click="selectedQuarter = q.quarter"
          >
            <div class="quarter-top">
              <span class="quarter-tag">{{ q.quarter }} {{ quarterlyYear }}</span>
              <span class="quarter-months">{{ q.periodLabel }}</span>
            </div>
            <div class="quarter-main">
              <span class="quarter-count-label">New Donors:</span>
              <strong class="quarter-count">{{ q.newDonors.length }}</strong>
            </div>
            <div class="quarter-progress-bar">
              <div
                class="quarter-progress-fill"
                :style="{ width: getQuarterPercent(q.newDonors.length) + '%' }"
              ></div>
            </div>
            <span class="quarter-percent-meta">{{ getQuarterPercent(q.newDonors.length) }}% of annual new donors</span>
          </div>
        </div>


        <!-- Detailed Quarterly New Donor List -->
        <div class="card-box">
          <div class="box-header">
            <h4 class="box-title">
              New Donors in {{ selectedQuarter }} {{ quarterlyYear }}
              ({{ activeQuarterRecord.periodLabel }})
            </h4>
            <span class="record-badge">{{ activeQuarterRecord.newDonors.length }} Registered</span>
          </div>

          <div class="table-wrap">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Donor Name</th>
                  <th>Blood Type</th>
                  <th>Age</th>
                  <th>Gender</th>
                  <th>Location</th>
                  <th>Contact</th>
                  <th>First Donation / Registered</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="activeQuarterRecord.newDonors.length === 0">
                  <td colspan="7" class="empty-state">
                    No new donors registered in {{ selectedQuarter }} {{ quarterlyYear }}.
                  </td>
                </tr>
                <tr v-for="donor in activeQuarterRecord.newDonors" :key="donor.id">
                  <td class="font-medium text-dark">{{ donor.fullName }}</td>
                  <td><span class="blood-type-badge">{{ donor.bloodType }}</span></td>
                  <td>{{ donor.age }}</td>
                  <td><span :class="['gender-tag', donor.gender.toLowerCase()]">{{ donor.gender }}</span></td>
                  <td>{{ donor.barangay }}, {{ donor.municipality }}</td>
                  <td>{{ donor.contact }}</td>
                  <td>{{ formatDate(donor.registeredDate) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ==================================================================== -->
      <!-- 3. YEARLY DONOR REPORT                                               -->
      <!-- ==================================================================== -->
      <section v-else-if="activeTab === 'yearly'" class="report-section">
        <div class="section-title-wrap">
          <div>
            <h3 class="section-heading">Yearly Donor Report</h3>
            <p class="section-subheading">
              Comprehensive annual statistics, demographic breakdowns, and location distributions for
              <strong>{{ yearlySelectedYear }}</strong>.
            </p>
          </div>

          <div class="filters-inline">
            <div class="filter-group">
              <label>Year:</label>
              <select v-model.number="yearlySelectedYear" class="filter-select">
                <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- High-level Annual KPIs -->
        <div class="kpi-grid">
          <div class="kpi-card">
            <span class="kpi-label">Total Annual Donors</span>
            <strong class="kpi-value text-red">{{ yearlyReportData.totalDonors }}</strong>
            <span class="kpi-meta">active in {{ yearlySelectedYear }}</span>
          </div>
          <div class="kpi-card">
            <span class="kpi-label">Total Donations</span>
            <strong class="kpi-value text-blue">{{ yearlyReportData.totalDonations }}</strong>
            <span class="kpi-meta">bags collected</span>
          </div>
          <div class="kpi-card">
            <span class="kpi-label">New Donors</span>
            <strong class="kpi-value text-green">{{ yearlyReportData.newDonorsCount }}</strong>
            <span class="kpi-meta">first-time in {{ yearlySelectedYear }}</span>
          </div>
          <div class="kpi-card">
            <span class="kpi-label">Repeat Donors</span>
            <strong class="kpi-value text-purple">{{ yearlyReportData.repeatDonorsCount }}</strong>
            <span class="kpi-meta">2+ lifetime donations</span>
          </div>
        </div>

        <!-- 4 Breakdown Modules Grid -->
        <div class="breakdown-grid">
          <!-- 1. Donors by Location -->
          <div class="card-box">
            <h4 class="box-title"><i class="ti ti-map-pin"></i> Donors by Location</h4>
            <div class="table-wrap">
              <table class="report-table compact">
                <thead>
                  <tr>
                    <th>Location</th>
                    <th>Donors</th>
                    <th>Donations</th>
                    <th>Share</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="loc in yearlyReportData.byLocation" :key="loc.name">
                    <td class="font-medium">{{ loc.name }}</td>
                    <td>{{ loc.donorCount }}</td>
                    <td>{{ loc.donationCount }} bags</td>
                    <td>
                      <div class="share-cell">
                        <div class="mini-bar">
                          <div class="mini-bar-fill" :style="{ width: loc.percentage + '%' }"></div>
                        </div>
                        <span class="share-pct">{{ loc.percentage }}%</span>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="yearlyReportData.byLocation.length === 0">
                    <td colspan="4" class="empty-state">No location data.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- 2. Donors by Blood Type -->
          <div class="card-box">
            <h4 class="box-title"><i class="ti ti-droplet"></i> Donors by Blood Type</h4>
            <div class="bt-summary-grid">
              <div
                v-for="bt in yearlyReportData.byBloodType"
                :key="bt.type"
                class="bt-stat-card"
              >
                <div class="bt-badge-large">{{ bt.type }}</div>
                <div class="bt-stat-details">
                  <strong>{{ bt.count }} Donor{{ bt.count !== 1 ? 's' : '' }}</strong>
                  <span>{{ bt.bags }} bags ({{ bt.percentage }}%)</span>
                </div>
              </div>
            </div>
          </div>

          <!-- 3. Donors by Age Group -->
          <div class="card-box">
            <h4 class="box-title"><i class="ti ti-users"></i> Donors by Age</h4>
            <div class="table-wrap">
              <table class="report-table compact">
                <thead>
                  <tr>
                    <th>Age Group</th>
                    <th>Donors</th>
                    <th>Percentage</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="age in yearlyReportData.byAgeGroup" :key="age.group">
                    <td class="font-medium">{{ age.group }}</td>
                    <td><strong>{{ age.count }}</strong></td>
                    <td>
                      <div class="share-cell">
                        <div class="mini-bar bg-blue">
                          <div class="mini-bar-fill" :style="{ width: age.percentage + '%' }"></div>
                        </div>
                        <span class="share-pct">{{ age.percentage }}%</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- 4. Donors by Gender -->
          <div class="card-box">
            <h4 class="box-title"><i class="ti ti-gender-intergender"></i> Donors by Gender</h4>
            <div class="gender-yearly-box">
              <div class="gender-yearly-metric">
                <div class="gender-icon male"><i class="ti ti-gender-male"></i></div>
                <div>
                  <span class="gender-label">Male</span>
                  <strong class="gender-val">{{ yearlyReportData.byGender.male }}</strong>
                  <span class="gender-pct">{{ yearlyReportData.byGender.malePct }}%</span>
                </div>
              </div>
              <div class="gender-yearly-metric">
                <div class="gender-icon female"><i class="ti ti-gender-female"></i></div>
                <div>
                  <span class="gender-label">Female</span>
                  <strong class="gender-val">{{ yearlyReportData.byGender.female }}</strong>
                  <span class="gender-pct">{{ yearlyReportData.byGender.femalePct }}%</span>
                </div>
              </div>
            </div>

            <div class="gender-split-bar">
              <div
                class="gender-bar-male"
                :style="{ width: yearlyReportData.byGender.malePct + '%' }"
                :title="`Male: ${yearlyReportData.byGender.malePct}%`"
              ></div>
              <div
                class="gender-bar-female"
                :style="{ width: yearlyReportData.byGender.femalePct + '%' }"
                :title="`Female: ${yearlyReportData.byGender.femalePct}%`"
              ></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ==================================================================== -->
      <!-- 4. DONOR AGE REPORT                                                  -->
      <!-- ==================================================================== -->
      <section v-else-if="activeTab === 'age'" class="report-section">
        <div class="section-title-wrap">
          <div>
            <h3 class="section-heading">Donor Age Report</h3>
            <p class="section-subheading">
              Categorization and statistical analysis of blood donors by structured age brackets (16–25, 26–35, 36–45, 46–55, 56+).
            </p>
          </div>

          <div class="filters-inline">
            <div class="filter-group">
              <label>Year Filter:</label>
              <select v-model="ageReportYear" class="filter-select">
                <option value="all">All-Time</option>
                <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Age Group Table -->
        <div class="card-box">
          <div class="box-header">
            <h4 class="box-title">Age Group Distribution Table</h4>
            <span class="record-badge">Total Donors Analyzed: {{ ageReportData.totalDonors }}</span>
          </div>

          <div class="table-wrap">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Age Group</th>
                  <th>Number of Donors</th>
                  <th>Total Donations</th>
                  <th>Percentage of Donors</th>
                  <th>Distribution Bar</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in ageReportData.groups"
                  :key="item.group"
                  :class="{ 'row-highlight': selectedAgeGroup === item.group }"
                  class="clickable-row"
                  @click="selectedAgeGroup = selectedAgeGroup === item.group ? null : item.group"
                >
                  <td>
                    <strong>{{ item.group }}</strong>
                    <span v-if="selectedAgeGroup === item.group" class="active-filter-tag">Selected</span>
                  </td>
                  <td><strong class="text-red">{{ item.donors.length }}</strong></td>
                  <td>{{ item.totalDonations }} bags</td>
                  <td>{{ item.percentage }}%</td>
                  <td>
                    <div class="progress-bar-wrap">
                      <div class="progress-bar-fill" :style="{ width: item.percentage + '%' }"></div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Donors inside selected age group -->
        <div v-if="selectedAgeGroup" class="card-box">
          <div class="box-header">
            <h4 class="box-title">Donors in Age Group {{ selectedAgeGroup }} ({{ filteredAgeGroupDonors.length }})</h4>
            <button class="btn-clear-filter" @click="selectedAgeGroup = null">
              <i class="ti ti-x"></i> Show All
            </button>
          </div>

          <div class="table-wrap">
            <table class="report-table compact">
              <thead>
                <tr>
                  <th>Donor Name</th>
                  <th>Exact Age</th>
                  <th>Blood Type</th>
                  <th>Gender</th>
                  <th>Location</th>
                  <th>Total Donations</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="d in filteredAgeGroupDonors" :key="d.id">
                  <td class="font-medium text-dark">{{ d.fullName }}</td>
                  <td>{{ d.age }} yrs old</td>
                  <td><span class="blood-type-badge">{{ d.bloodType }}</span></td>
                  <td><span :class="['gender-tag', d.gender.toLowerCase()]">{{ d.gender }}</span></td>
                  <td>{{ d.barangay }}, {{ d.municipality }}</td>
                  <td>{{ d.donationHistory.length }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ==================================================================== -->
      <!-- 5. GENDER REPORT                                                     -->
      <!-- ==================================================================== -->
      <section v-else-if="activeTab === 'gender'" class="report-section">
        <div class="section-title-wrap">
          <div>
            <h3 class="section-heading">Gender Report</h3>
            <p class="section-subheading">
              Automatic breakdown and comparative metrics of male and female blood donors, filterable by month and year.
            </p>
          </div>

          <div class="filters-inline">
            <div class="filter-group">
              <label>Year:</label>
              <select v-model.number="genderFilter.year" class="filter-select">
                <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
            <div class="filter-group">
              <label>Month:</label>
              <select v-model="genderFilter.month" class="filter-select">
                <option value="all">All Months (Annual)</option>
                <option v-for="(m, idx) in monthNames" :key="m" :value="idx + 1">{{ m }}</option>
              </select>
            </div>
          </div>
        </div>


        <!-- Filtered Donors Table -->
        <div class="card-box">
          <div class="box-header">
            <h4 class="box-title">Donor Records by Gender</h4>
            <span class="record-badge">{{ genderReportData.donorsList.length }} Records Found</span>
          </div>

          <div class="table-wrap">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Donor Name</th>
                  <th>Gender</th>
                  <th>Blood Type</th>
                  <th>Age</th>
                  <th>Location</th>
                  <th>Contact</th>
                  <th>Last Donation</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="genderReportData.donorsList.length === 0">
                  <td colspan="7" class="empty-state">No donors found for this period.</td>
                </tr>
                <tr v-for="d in genderReportData.donorsList" :key="d.id">
                  <td class="font-medium text-dark">{{ d.fullName }}</td>
                  <td><span :class="['gender-tag', d.gender.toLowerCase()]">{{ d.gender }}</span></td>
                  <td><span class="blood-type-badge">{{ d.bloodType }}</span></td>
                  <td>{{ d.age }}</td>
                  <td>{{ d.barangay }}, {{ d.municipality }}</td>
                  <td>{{ d.contact }}</td>
                  <td>{{ formatDate(d.donationHistory[d.donationHistory.length - 1]?.date) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ==================================================================== -->
      <!-- 6. BLOOD CONSUMPTION REPORT (AGGREGATED NON-IDENTIFYING)             -->
      <!-- ==================================================================== -->
      <section v-else-if="activeTab === 'consumption'" class="report-section">
        <div class="section-title-wrap">
          <div>
            <h3 class="section-heading">Blood Consumption Report</h3>
            <p class="section-subheading">
              Aggregated monthly consumption metrics by location, blood type, volume (bags), and reporting period.
              <span class="privacy-note">Strictly anonymized statistical data — no patient identifiers.</span>
            </p>
          </div>

          <div class="filters-inline">
            <div class="filter-group">
              <label>Location:</label>
              <input
                v-model="consumptionFilter.location"
                type="text"
                placeholder="Filter location..."
                class="filter-input"
              />
            </div>
            <div class="filter-group">
              <label>Blood Type:</label>
              <select v-model="consumptionFilter.bloodType" class="filter-select">
                <option value="">All Types</option>
                <option v-for="bt in bloodTypes" :key="bt" :value="bt">{{ bt }}</option>
              </select>
            </div>
            <div class="filter-group">
              <label>Month:</label>
              <select v-model="consumptionFilter.month" class="filter-select">
                <option value="">All Months</option>
                <option v-for="(m, idx) in monthNames" :key="m" :value="idx + 1">{{ m }}</option>
              </select>
            </div>
            <div class="filter-group">
              <label>Year:</label>
              <select v-model="consumptionFilter.year" class="filter-select">
                <option value="">All Years</option>
                <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Aggregate KPIs -->
        <div class="kpi-grid">
          <div class="kpi-card">
            <span class="kpi-label">Total Bags Consumed</span>
            <strong class="kpi-value text-red">{{ consumptionReportData.totalBags }}</strong>
            <span class="kpi-meta">in selected filter</span>
          </div>
          <div class="kpi-card">
            <span class="kpi-label">Reporting Locations</span>
            <strong class="kpi-value text-blue">{{ consumptionReportData.locationCount }}</strong>
            <span class="kpi-meta">facilities / municipalities</span>
          </div>
          <div class="kpi-card">
            <span class="kpi-label">Highest Consumed Type</span>
            <strong class="kpi-value text-green">{{ consumptionReportData.topBloodType || '—' }}</strong>
            <span class="kpi-meta">most utilized</span>
          </div>
          <div class="kpi-card">
            <span class="kpi-label">Total Report Batches</span>
            <strong class="kpi-value text-purple">{{ consumptionReportData.filteredRecords.length }}</strong>
            <span class="kpi-meta">submitted records</span>
          </div>
        </div>

        <!-- Blood Type Summary Pills -->
        <div class="card-box">
          <h4 class="box-title">Aggregated Volume by Blood Type</h4>
          <div class="pill-group">
            <div
              v-for="item in consumptionReportData.byBloodType"
              :key="item.type"
              class="breakdown-pill"
            >
              <span class="badge-bt">{{ item.type }}</span>
              <span class="pill-count"><strong>{{ item.total }}</strong> bags</span>
            </div>
          </div>
        </div>

        <!-- Aggregated Records Table -->
        <div class="card-box">
          <div class="box-header">
            <h4 class="box-title">Aggregated Consumption Records</h4>
            <span class="record-badge">{{ consumptionReportData.filteredRecords.length }} Rows</span>
          </div>

          <div class="table-wrap">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Reporting Period</th>
                  <th>Location</th>
                  <th>Blood Type</th>
                  <th>Number of Bags</th>
                  <th>Date Submitted</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="consumptionReportData.filteredRecords.length === 0">
                  <td colspan="5" class="empty-state">No consumption records match your filters.</td>
                </tr>
                <tr v-for="rec in consumptionReportData.filteredRecords" :key="rec.id">
                  <td><strong>{{ rec.reportingPeriod || `${monthNames[rec.month - 1]} ${rec.year}` }}</strong></td>
                  <td>{{ rec.location }}</td>
                  <td><span class="blood-type-badge">{{ rec.bloodType }}</span></td>
                  <td><strong class="text-red">{{ rec.numberOfBags }}</strong></td>
                  <td>{{ formatDate(rec.dateSubmitted) }}</td>
                </tr>
              </tbody>
              <tfoot v-if="consumptionReportData.filteredRecords.length > 0">
                <tr>
                  <td colspan="3" class="total-label">Total Consumed Bags:</td>
                  <td class="total-val">{{ consumptionReportData.totalBags }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </section>
    </div>
  </ModulePage>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRoute } from 'vue-router'
import ModulePage from '../../components/dashboard/ModulePage.vue'
import {
  useBloodBankStore,
  monthNames,
  bloodTypes,
} from '../../stores/bloodBankStore'

const route = useRoute()
const { donors, consumptionRecords, allDonationsFlattened } = useBloodBankStore()

// ==========================================
// Tabs Definition
// ==========================================
const reportTabs = [
  { id: 'monthly', label: 'Monthly Donor Report', icon: 'ti ti-calendar-event' },
  { id: 'quarterly', label: 'Quarterly New Donor Report', icon: 'ti ti-chart-bar' },
  { id: 'yearly', label: 'Yearly Donor Report', icon: 'ti ti-trending-up' },
  { id: 'age', label: 'Donor Age Report', icon: 'ti ti-users' },
  { id: 'gender', label: 'Gender Report', icon: 'ti ti-gender-intergender' },
  { id: 'consumption', label: 'Blood Consumption Report', icon: 'ti ti-droplet' },
]

// Active Tab state
const initialTab = computed(() => {
  if (route.query.tab) return route.query.tab
  if (route.path.includes('monthly')) return 'monthly'
  if (route.path.includes('quarterly')) return 'quarterly'
  if (route.path.includes('yearly')) return 'yearly'
  if (route.path.includes('age')) return 'age'
  if (route.path.includes('gender')) return 'gender'
  if (route.path.includes('consumption')) return 'consumption'
  return 'monthly'
})

const activeTab = ref(initialTab.value)
const availableYears = [2024, 2025, 2026, 2027]

function formatDate(dateStr) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

function printReport() {
  window.print()
}

// ====================================================================
// 1. MONTHLY DONOR REPORT LOGIC
// ====================================================================
const currentMonth = new Date().getMonth() + 1
const currentYear = new Date().getFullYear()

const monthlyFilter = reactive({
  month: currentMonth,
  year: currentYear,
})

const monthlyDonations = computed(() => {
  return allDonationsFlattened.value.filter(
    (d) => d.year === monthlyFilter.year && d.month === monthlyFilter.month
  )
})

const monthlyStats = computed(() => {
  const donations = monthlyDonations.value
  const uniqueDonors = new Set(donations.map((d) => d.donorId))
  const uniqueLocations = new Set(donations.map((d) => d.location || d.barangay))
  const totalBags = donations.reduce((sum, d) => sum + (d.bags || 1), 0)

  // Blood type counts
  const btMap = {}
  donations.forEach((d) => {
    btMap[d.bloodType] = (btMap[d.bloodType] || 0) + 1
  })

  let topBt = null
  let maxCount = 0
  Object.entries(btMap).forEach(([bt, cnt]) => {
    if (cnt > maxCount) {
      maxCount = cnt
      topBt = bt
    }
  })

  const breakdown = Object.entries(btMap).map(([type, count]) => ({ type, count }))

  return {
    uniqueDonorsCount: uniqueDonors.size,
    totalBags,
    topBloodType: topBt,
    uniqueLocationsCount: uniqueLocations.size,
    bloodTypeBreakdown: breakdown,
  }
})

// ====================================================================
// 2. QUARTERLY NEW DONOR REPORT LOGIC
// ====================================================================
const quarterlyYear = ref(currentYear)
const selectedQuarter = ref('Q1')

const quarterlyData = computed(() => {
  const year = quarterlyYear.value
  const quarters = [
    { quarter: 'Q1', months: [1, 2, 3], periodLabel: 'January to March' },
    { quarter: 'Q2', months: [4, 5, 6], periodLabel: 'April to June' },
    { quarter: 'Q3', months: [7, 8, 9], periodLabel: 'July to September' },
    { quarter: 'Q4', months: [10, 11, 12], periodLabel: 'October to December' },
  ]

  return quarters.map((q) => {
    // A donor is "New" if their registeredDate (or first donation) falls in this quarter & year
    const newDonors = donors.value.filter((donor) => {
      const reg = new Date(donor.registeredDate)
      return (
        reg.getFullYear() === year &&
        q.months.includes(reg.getMonth() + 1)
      )
    })
    return {
      ...q,
      newDonors,
    }
  })
})

const totalNewDonorsInYear = computed(() => {
  return quarterlyData.value.reduce((sum, q) => sum + q.newDonors.length, 0)
})

function getQuarterPercent(count) {
  if (!totalNewDonorsInYear.value) return 0
  return Math.round((count / totalNewDonorsInYear.value) * 100)
}

const activeQuarterRecord = computed(() => {
  return quarterlyData.value.find((q) => q.quarter === selectedQuarter.value) || quarterlyData.value[0]
})

// ====================================================================
// 3. YEARLY DONOR REPORT LOGIC
// ====================================================================
const yearlySelectedYear = ref(currentYear)

const yearlyReportData = computed(() => {
  const year = yearlySelectedYear.value

  // All donations in this year
  const yearDonations = allDonationsFlattened.value.filter((d) => d.year === year)
  const activeDonorIds = new Set(yearDonations.map((d) => d.donorId))
  const yearDonors = donors.value.filter((d) => activeDonorIds.has(d.id))

  // New donors registered in this year
  const newDonors = donors.value.filter((d) => new Date(d.registeredDate).getFullYear() === year)
  const repeatDonors = yearDonors.filter((d) => d.donationHistory.length > 1)

  // 1. By Location
  const locMap = {}
  yearDonations.forEach((d) => {
    const loc = d.location || d.barangay || 'General'
    if (!locMap[loc]) locMap[loc] = { donors: new Set(), bags: 0 }
    locMap[loc].donors.add(d.donorId)
    locMap[loc].bags += d.bags || 1
  })

  const totalDonations = yearDonations.reduce((sum, d) => sum + (d.bags || 1), 0)

  const byLocation = Object.entries(locMap).map(([name, data]) => ({
    name,
    donorCount: data.donors.size,
    donationCount: data.bags,
    percentage: totalDonations ? Math.round((data.bags / totalDonations) * 100) : 0,
  })).sort((a, b) => b.donationCount - a.donationCount)

  // 2. By Blood Type
  const btMap = {}
  bloodTypes.forEach((bt) => {
    btMap[bt] = { count: 0, bags: 0 }
  })
  yearDonations.forEach((d) => {
    if (!btMap[d.bloodType]) btMap[d.bloodType] = { count: 0, bags: 0 }
    btMap[d.bloodType].bags += d.bags || 1
  })
  yearDonors.forEach((d) => {
    if (btMap[d.bloodType]) btMap[d.bloodType].count += 1
  })

  const byBloodType = Object.entries(btMap).map(([type, val]) => ({
    type,
    count: val.count,
    bags: val.bags,
    percentage: yearDonors.length ? Math.round((val.count / yearDonors.length) * 100) : 0,
  }))

  // 3. By Age Group
  const ageBrackets = [
    { group: '16–25', min: 16, max: 25 },
    { group: '26–35', min: 26, max: 35 },
    { group: '36–45', min: 36, max: 45 },
    { group: '46–55', min: 46, max: 55 },
    { group: '56+', min: 56, max: 120 },
  ]
  const byAgeGroup = ageBrackets.map((b) => {
    const matching = yearDonors.filter((d) => d.age >= b.min && d.age <= b.max)
    return {
      group: b.group,
      count: matching.length,
      percentage: yearDonors.length ? Math.round((matching.length / yearDonors.length) * 100) : 0,
    }
  })

  // 4. By Gender
  const maleCount = yearDonors.filter((d) => d.gender.toLowerCase() === 'male').length
  const femaleCount = yearDonors.filter((d) => d.gender.toLowerCase() === 'female').length
  const totalCount = yearDonors.length
  const malePct = totalCount ? Math.round((maleCount / totalCount) * 100) : 0
  const femalePct = totalCount ? 100 - malePct : 0

  return {
    totalDonors: yearDonors.length,
    totalDonations,
    newDonorsCount: newDonors.length,
    repeatDonorsCount: repeatDonors.length,
    byLocation,
    byBloodType,
    byAgeGroup,
    byGender: {
      male: maleCount,
      female: femaleCount,
      malePct,
      femalePct,
    },
  }
})

// ====================================================================
// 4. DONOR AGE REPORT LOGIC
// ====================================================================
const ageReportYear = ref('all')
const selectedAgeGroup = ref(null)

const ageReportData = computed(() => {
  let donorPool = donors.value
  if (ageReportYear.value !== 'all') {
    const yr = Number(ageReportYear.value)
    donorPool = donors.value.filter((d) =>
      d.donationHistory.some((item) => new Date(item.date).getFullYear() === yr)
    )
  }

  const ageBrackets = [
    { group: '16–25', min: 16, max: 25 },
    { group: '26–35', min: 26, max: 35 },
    { group: '36–45', min: 36, max: 45 },
    { group: '46–55', min: 46, max: 55 },
    { group: '56+', min: 56, max: 120 },
  ]

  const total = donorPool.length

  const groups = ageBrackets.map((b) => {
    const list = donorPool.filter((d) => d.age >= b.min && d.age <= b.max)
    const totalDonations = list.reduce((sum, d) => sum + d.donationHistory.length, 0)
    return {
      group: b.group,
      donors: list,
      totalDonations,
      percentage: total ? Math.round((list.length / total) * 100) : 0,
    }
  })

  return {
    totalDonors: total,
    groups,
  }
})

const filteredAgeGroupDonors = computed(() => {
  if (!selectedAgeGroup.value) return []
  const group = ageReportData.value.groups.find((g) => g.group === selectedAgeGroup.value)
  return group ? group.donors : []
})

// ====================================================================
// 5. GENDER REPORT LOGIC
// ====================================================================
const genderFilter = reactive({
  year: currentYear,
  month: 'all',
})

const genderReportData = computed(() => {
  const yr = genderFilter.year
  const mo = genderFilter.month

  let activeDonations = allDonationsFlattened.value.filter((d) => d.year === yr)
  if (mo !== 'all') {
    activeDonations = activeDonations.filter((d) => d.month === Number(mo))
  }

  const donorIds = new Set(activeDonations.map((d) => d.donorId))
  const filteredDonors = donors.value.filter((d) => donorIds.has(d.id))

  const maleDonors = filteredDonors.filter((d) => d.gender.toLowerCase() === 'male')
  const femaleDonors = filteredDonors.filter((d) => d.gender.toLowerCase() === 'female')

  const maleDonations = activeDonations.filter((d) => d.gender.toLowerCase() === 'male').length
  const femaleDonations = activeDonations.filter((d) => d.gender.toLowerCase() === 'female').length

  const total = filteredDonors.length
  const malePct = total ? Math.round((maleDonors.length / total) * 100) : 0
  const femalePct = total ? 100 - malePct : 0

  return {
    totalCount: total,
    totalDonations: activeDonations.length,
    maleCount: maleDonors.length,
    femaleCount: femaleDonors.length,
    malePct,
    femalePct,
    maleDonations,
    femaleDonations,
    donorsList: filteredDonors,
  }
})

// ====================================================================
// 6. BLOOD CONSUMPTION REPORT LOGIC (AGGREGATED ONLY)
// ====================================================================
const consumptionFilter = reactive({
  location: '',
  bloodType: '',
  month: '',
  year: '',
})

const consumptionReportData = computed(() => {
  const filtered = consumptionRecords.value
    .filter((r) => !consumptionFilter.location || r.location.toLowerCase().includes(consumptionFilter.location.toLowerCase()))
    .filter((r) => !consumptionFilter.bloodType || r.bloodType === consumptionFilter.bloodType)
    .filter((r) => !consumptionFilter.month || r.month === Number(consumptionFilter.month))
    .filter((r) => !consumptionFilter.year || r.year === Number(consumptionFilter.year))
    .sort((a, b) => b.year - a.year || b.month - a.month)

  const totalBags = filtered.reduce((sum, r) => sum + r.numberOfBags, 0)
  const locationsSet = new Set(filtered.map((r) => r.location))

  const btMap = {}
  filtered.forEach((r) => {
    btMap[r.bloodType] = (btMap[r.bloodType] || 0) + r.numberOfBags
  })

  let topBt = null
  let max = 0
  Object.entries(btMap).forEach(([bt, total]) => {
    if (total > max) {
      max = total
      topBt = bt
    }
  })

  const byBloodType = Object.entries(btMap).map(([type, total]) => ({ type, total }))

  return {
    filteredRecords: filtered,
    totalBags,
    locationCount: locationsSet.size,
    topBloodType: topBt,
    byBloodType,
  }
})
</script>

<style scoped>
.reports-module {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* ===================== Header & Tabs ===================== */
.reports-header-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  border-bottom: 2px solid #edf2f7;
  padding-bottom: 1rem;
}

.report-select-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.report-select-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.85rem;
  font-weight: 700;
  color: #4a5568;
}

.report-select-label i {
  font-size: 1.1rem;
  color: #c0392b;
}

.select-wrapper {
  position: relative;
  display: inline-flex;
  align-items: center;
}

.report-dropdown-select {
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  padding: 8px 36px 8px 14px;
  border-radius: 8px;
  border: 1.5px solid #cbd5e0;
  background: #ffffff;
  color: #1a202c;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  min-width: 250px;
  transition: all 0.2s;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.report-dropdown-select:hover {
  border-color: #a0aec0;
}

.report-dropdown-select:focus {
  outline: none;
  border-color: #c0392b;
  box-shadow: 0 0 0 3px rgba(192, 57, 43, 0.15);
}

.select-chevron {
  position: absolute;
  right: 12px;
  pointer-events: none;
  color: #718096;
  font-size: 0.85rem;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.live-sync-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  color: #2b6cb0;
  background: #ebf8ff;
  padding: 5px 10px;
  border-radius: 999px;
}

.pulse-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #3182ce;
  animation: pulse 1.8s infinite;
}

@keyframes pulse {
  0% { transform: scale(0.9); opacity: 0.7; }
  50% { transform: scale(1.3); opacity: 1; }
  100% { transform: scale(0.9); opacity: 0.7; }
}

.btn-print {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  border: 1px solid #cbd5e0;
  border-radius: 6px;
  background: #fff;
  color: #2d3748;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}
.btn-print:hover {
  background: #edf2f7;
}

/* ===================== Sections & Headings ===================== */
.report-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.section-title-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.section-heading {
  font-size: 1.2rem;
  font-weight: 700;
  color: #1a202c;
  margin-bottom: 0.2rem;
}

.section-subheading {
  font-size: 0.85rem;
  color: #718096;
  margin: 0;
}

.privacy-note {
  display: block;
  font-size: 0.78rem;
  color: #e53e3e;
  margin-top: 2px;
  font-weight: 500;
}

/* ===================== Filters ===================== */
.filters-inline {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.82rem;
  color: #4a5568;
  font-weight: 500;
}

.filter-select,
.filter-input {
  padding: 6px 10px;
  border: 1px solid #cbd5e0;
  border-radius: 6px;
  font-size: 0.82rem;
  background: #fff;
  outline: none;
}

/* ===================== KPI Cards ===================== */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

.kpi-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.kpi-label {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #718096;
}

.kpi-value {
  font-size: 1.6rem;
  font-weight: 800;
  line-height: 1.2;
}

.kpi-meta {
  font-size: 0.75rem;
  color: #a0aec0;
}

.text-red { color: #c0392b; }
.text-blue { color: #2563eb; }
.text-green { color: #16a34a; }
.text-purple { color: #7c3aed; }
.text-dark { color: #1a202c; }
.font-medium { font-weight: 500; }

/* ===================== Card Box ===================== */
.card-box {
  background: #fff;
  border: 1px solid #edf2f7;
  border-radius: 10px;
  padding: 1.25rem;
  box-shadow: 0 1px 4px rgba(0,0,0,0.03);
}

.box-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
  gap: 10px;
}

.box-title {
  font-size: 0.95rem;
  font-weight: 700;
  color: #2d3748;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 6px;
}

.record-badge {
  font-size: 0.75rem;
  background: #edf2f7;
  color: #4a5568;
  padding: 3px 8px;
  border-radius: 6px;
  font-weight: 600;
}

/* ===================== Tables ===================== */
.table-wrap {
  overflow-x: auto;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}

.report-table th {
  text-align: left;
  padding: 9px 12px;
  background: #f8fafc;
  font-weight: 600;
  color: #4a5568;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}

.report-table td {
  padding: 9px 12px;
  border-bottom: 1px solid #f1f5f9;
  color: #4a5568;
  vertical-align: middle;
}

.report-table.compact th,
.report-table.compact td {
  padding: 7px 10px;
  font-size: 0.82rem;
}

.clickable-row {
  cursor: pointer;
  transition: background 0.15s;
}
.clickable-row:hover {
  background: #f8fafc;
}
.row-highlight {
  background: #fff5f5 !important;
}

.active-filter-tag {
  font-size: 0.7rem;
  background: #c0392b;
  color: #fff;
  padding: 2px 6px;
  border-radius: 4px;
  margin-left: 6px;
}

.report-table tfoot td {
  font-weight: 700;
  padding: 10px 12px;
  background: #f8fafc;
  border-top: 2px solid #e2e8f0;
}
.total-label {
  text-align: right;
  color: #2d3748;
}
.total-val {
  color: #c0392b;
  font-size: 1rem;
}

.empty-state {
  text-align: center;
  color: #a0aec0;
  padding: 2rem !important;
  font-style: italic;
}

/* ===================== Badges & Tags ===================== */
.blood-type-badge {
  display: inline-block;
  padding: 2px 7px;
  border-radius: 4px;
  background: #fdecec;
  color: #c0392b;
  font-weight: 700;
  font-size: 0.75rem;
}

.gender-tag {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}
.gender-tag.male {
  background: #ebf8ff;
  color: #2b6cb0;
}
.gender-tag.female {
  background: #fdf2f8;
  color: #db2777;
}

.pill-group {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 0.75rem;
}

.breakdown-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 5px 10px;
  border-radius: 8px;
}
.badge-bt {
  font-size: 0.75rem;
  font-weight: 700;
  color: #c0392b;
}
.pill-count {
  font-size: 0.8rem;
  color: #4a5568;
}

/* ===================== Quarterly Grid ===================== */
.quarter-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

.quarter-card {
  background: #fff;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  padding: 1.25rem;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.quarter-card:hover {
  border-color: #cbd5e0;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.quarter-card--active {
  border-color: #c0392b;
  background: #fffafa;
  box-shadow: 0 4px 12px rgba(192, 57, 43, 0.12);
}

.quarter-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.quarter-tag {
  font-size: 0.9rem;
  font-weight: 700;
  color: #2d3748;
}

.quarter-months {
  font-size: 0.72rem;
  color: #718096;
}

.quarter-main {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
}

.quarter-count-label {
  font-size: 0.8rem;
  color: #718096;
}

.quarter-count {
  font-size: 1.8rem;
  font-weight: 800;
  color: #c0392b;
}

.quarter-progress-bar {
  width: 100%;
  height: 6px;
  background: #edf2f7;
  border-radius: 999px;
  overflow: hidden;
}

.quarter-progress-fill {
  height: 100%;
  background: #c0392b;
  border-radius: 999px;
  transition: width 0.3s;
}

.quarter-percent-meta {
  font-size: 0.72rem;
  color: #a0aec0;
}

.annual-highlight-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #ebf8ff;
  border: 1px solid #bee3f8;
  border-radius: 10px;
  padding: 1rem 1.25rem;
}

.banner-icon {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: #3182ce;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
}

.banner-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.banner-text strong {
  color: #2b6cb0;
  font-size: 0.95rem;
}
.banner-text span {
  font-size: 0.8rem;
  color: #4a5568;
}

/* ===================== Yearly Breakdowns ===================== */
.breakdown-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;
}

.share-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.mini-bar {
  flex: 1;
  height: 6px;
  background: #edf2f7;
  border-radius: 999px;
  overflow: hidden;
}
.mini-bar.bg-blue .mini-bar-fill {
  background: #2563eb;
}

.mini-bar-fill {
  height: 100%;
  background: #c0392b;
  border-radius: 999px;
}

.share-pct {
  font-size: 0.75rem;
  color: #718096;
  width: 32px;
  text-align: right;
}

.bt-summary-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
  margin-top: 0.5rem;
}

.bt-stat-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.bt-badge-large {
  font-size: 0.85rem;
  font-weight: 800;
  color: #c0392b;
  background: #fdecec;
  padding: 4px 8px;
  border-radius: 6px;
}

.bt-stat-details {
  display: flex;
  flex-direction: column;
}
.bt-stat-details strong {
  font-size: 0.82rem;
  color: #2d3748;
}
.bt-stat-details span {
  font-size: 0.72rem;
  color: #718096;
}

/* ===================== Gender Components ===================== */
.gender-cards-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

.gender-stat-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.gender-card-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.gender-card-icon {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
}
.male-card .gender-card-icon {
  background: #ebf8ff;
  color: #2b6cb0;
}
.female-card .gender-card-icon {
  background: #fdf2f8;
  color: #db2777;
}
.total-card .gender-card-icon {
  background: #f0fdf4;
  color: #16a34a;
}

.gender-card-title {
  font-size: 0.85rem;
  font-weight: 600;
  color: #4a5568;
}

.gender-big-count {
  font-size: 2rem;
  font-weight: 800;
  line-height: 1.1;
  color: #1a202c;
}

.gender-meta-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.78rem;
  color: #718096;
  margin-top: 4px;
}

.gender-yearly-box {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 0.5rem;
  margin-bottom: 0.75rem;
}

.gender-yearly-metric {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  background: #f8fafc;
  border-radius: 8px;
}
.gender-icon {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
}
.gender-icon.male { background: #ebf8ff; color: #2b6cb0; }
.gender-icon.female { background: #fdf2f8; color: #db2777; }

.gender-label { font-size: 0.75rem; color: #718096; display: block; }
.gender-val { font-size: 1.1rem; font-weight: 700; color: #2d3748; }
.gender-pct { font-size: 0.75rem; color: #a0aec0; margin-left: 4px; }

.gender-split-bar {
  display: flex;
  height: 12px;
  border-radius: 999px;
  overflow: hidden;
  margin-top: 0.5rem;
}
.gender-split-bar.large {
  height: 28px;
  font-size: 0.75rem;
  font-weight: 600;
  color: #fff;
}
.gender-split-bar.large > div {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 8px;
  white-space: nowrap;
}

.gender-bar-male {
  background: #3182ce;
  transition: width 0.3s;
}
.gender-bar-female {
  background: #d53f8c;
  transition: width 0.3s;
}

/* ===================== Age Group Progress ===================== */
.progress-bar-wrap {
  width: 140px;
  height: 8px;
  background: #edf2f7;
  border-radius: 999px;
  overflow: hidden;
}
.progress-bar-fill {
  height: 100%;
  background: #c0392b;
  border-radius: 999px;
}

.btn-clear-filter {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 4px;
  border: 1px solid #cbd5e0;
  background: #fff;
  font-size: 0.75rem;
  cursor: pointer;
}
.btn-clear-filter:hover { background: #edf2f7; }

/* ===================== Print Styles ===================== */
@media print {
  .report-select-group,
  .btn-print,
  .filters-inline,
  .live-sync-badge {
    display: none !important;
  }
  .card-box {
    box-shadow: none !important;
    border: 1px solid #ccc !important;
  }
}

/* ===================== Responsive ===================== */
@media (max-width: 1024px) {
  .kpi-grid,
  .quarter-grid,
  .gender-cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .breakdown-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .kpi-grid,
  .quarter-grid,
  .gender-cards-grid {
    grid-template-columns: 1fr;
  }
  .section-title-wrap {
    flex-direction: column;
    align-items: stretch;
  }
  .filters-inline {
    width: 100%;
  }
}
</style>
