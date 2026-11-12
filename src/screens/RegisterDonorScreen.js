import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import DateTimePicker from '@react-native-community/datetimepicker';
import { fetchDonor, fetchDonorMeta, registerDonor, updateDonor } from '../api/client';
import { capitalizeWords, composeMbdDate, splitMbdDate, titleCaseText } from '../collectionForm';

const SUFFIXES = ['None', 'Jr.', 'Sr.', 'II', 'III', 'IV'];
const GENDERS = ['Male', 'Female'];
const DEFERRAL_REASONS = ['Underweight', 'Low Hemoglobin', 'Lack of Sleep', 'Elevated BP', 'Custom Reason'];
const BLOOD_TYPES = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

function donorNameParts(donor) {
  if (donor.first_name || donor.last_name) {
    return {
      firstName: donor.first_name ?? '',
      middleName: donor.middle_name ?? '',
      lastName: donor.last_name ?? '',
      suffix: donor.suffix ?? 'None',
    };
  }

  return { firstName: '', middleName: '', lastName: '', suffix: 'None' };
}

function parseDate(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return null;
  }

  const [year, month, day] = value.split('-').map(Number);
  const date = new Date(year, month - 1, day);

  if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
    return null;
  }

  return date;
}

function formatDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

export default function RegisterDonorScreen({ navigation, route }) {
  const donorId = route.params?.donorId;
  const [meta, setMeta] = useState(null);
  const [fullName, setFullName] = useState('');
  const [structuredName, setStructuredName] = useState(true);
  const [firstName, setFirstName] = useState('');
  const [middleName, setMiddleName] = useState('');
  const [lastName, setLastName] = useState('');
  const [suffix, setSuffix] = useState('None');
  const [bloodType, setBloodType] = useState('');
  const [age, setAge] = useState('');
  const [gender, setGender] = useState('');
  const [contact, setContact] = useState('');
  const [barangay, setBarangay] = useState('');
  const [municipality, setMunicipality] = useState('');
  const [province, setProvince] = useState('Dinagat Islands');
  const [mbdType, setMbdType] = useState('MBD');
  const [mbdOn, setMbdOn] = useState(() => formatDate(new Date()));
  const [mbdDatePickerVisible, setMbdDatePickerVisible] = useState(false);
  const [pendingMbdDate, setPendingMbdDate] = useState(new Date());
  const [status, setStatus] = useState('passed');
  const [serialNumber, setSerialNumber] = useState('');
  const [reservedForPatient, setReservedForPatient] = useState('');
  const [deferralReason, setDeferralReason] = useState('');
  const [customDeferralReason, setCustomDeferralReason] = useState('');
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(Boolean(donorId));

  useEffect(() => {
    fetchDonorMeta()
      .then(setMeta)
      .catch((error) => setMessage(error.message));
  }, []);

  useEffect(() => {
    if (!donorId) {
      return undefined;
    }

    let isActive = true;
    setLoading(true);

    fetchDonor(donorId)
      .then((donor) => {
        if (!isActive) {
          return;
        }

        const name = donorNameParts(donor);
        setStructuredName(Boolean(donor.first_name || donor.last_name));
        setFullName(donor.full_name ?? donor.name ?? '');
        setFirstName(name.firstName);
        setMiddleName(name.middleName);
        setLastName(name.lastName);
        setSuffix(name.suffix);
        setBloodType(donor.blood_type ?? 'O+');
        setAge(String(donor.age ?? ''));
        setGender(donor.gender ?? 'Male');
        setContact(donor.contact ?? '');
        setBarangay(donor.barangay ?? '');
        setMunicipality(donor.municipality ?? '');
        setProvince(donor.province ?? '');
        const latestDonation = donor.latest_donation;
        setStatus(latestDonation?.status_value ?? '');
        if (latestDonation) {
          const mbd = splitMbdDate(latestDonation.mbd_date ?? '');
          setMbdType(mbd.type);
          setMbdOn(mbd.date || formatDate(new Date()));
          setSerialNumber(latestDonation.serial_number ?? '');
          setReservedForPatient(latestDonation.reserved_for_px ?? '');
          const reason = latestDonation.reason_for_deferral ?? '';
          if (reason && DEFERRAL_REASONS.includes(reason)) {
            setDeferralReason(reason);
            setCustomDeferralReason('');
          } else if (reason) {
            setDeferralReason('Custom Reason');
            setCustomDeferralReason(reason);
          }
        }
      })
      .catch((error) => {
        if (isActive) {
          setMessage(error.message);
        }
      })
      .finally(() => {
        if (isActive) {
          setLoading(false);
        }
      });

    return () => {
      isActive = false;
    };
  }, [donorId]);

  const openMbdDatePicker = () => {
    setPendingMbdDate(parseDate(mbdOn) ?? new Date());
    setMbdDatePickerVisible(true);
  };

  const handleMbdDatePickerChange = (event, selectedDate) => {
    if (Platform.OS === 'android') {
      setMbdDatePickerVisible(false);
      if (event.type !== 'set' || !selectedDate) {
        return;
      }

      setMbdOn(formatDate(selectedDate));
      return;
    }

    if (selectedDate) {
      setPendingMbdDate(selectedDate);
    }
  };

  const saveDonor = async () => {
    setMessage('');
    if ((!donorId || status === 'passed' || status === 'deferred') && !parseDate(mbdOn)) {
      setMessage('Enter a valid MBD / Date in YYYY-MM-DD format.');
      return;
    }
    setSaving(true);
    const knownBarangays = meta?.places?.[municipality] ?? [];
    const savedBarangay = knownBarangays.length > 0 ? barangay.trim() : titleCaseText(barangay);
    const savedMbd = composeMbdDate(mbdType, mbdOn);
    try {
      if (donorId) {
        await updateDonor(donorId, {
          ...(structuredName ? {
            first_name: titleCaseText(firstName),
            middle_name: titleCaseText(middleName),
            last_name: titleCaseText(lastName),
            suffix: suffix === 'None' ? '' : suffix,
          } : { full_name: titleCaseText(fullName) }),
          blood_type: bloodType,
          age: age ? parseInt(age, 10) : null,
          gender,
          contact: contact.trim(),
          barangay: savedBarangay,
          municipality: municipality.trim(),
          province: province.trim(),
          ...(status === 'passed' || status === 'deferred' ? {
            mbd_type: mbdType,
            mbd_on: mbdOn,
            mbd_date: savedMbd,
            status,
            serial_number: status === 'passed' ? serialNumber.trim() : '',
            reserved_for_px: status === 'passed' ? titleCaseText(reservedForPatient) : '',
            reason_for_deferral: status === 'deferred' ? deferralReason : '',
            reason_for_deferral_custom: status === 'deferred' && deferralReason === 'Custom Reason'
              ? titleCaseText(customDeferralReason)
              : '',
          } : {}),
        });
        navigation.goBack();
        return;
      }

      const data = await registerDonor({
        first_name: titleCaseText(firstName),
        middle_name: titleCaseText(middleName),
        last_name: titleCaseText(lastName),
        suffix: suffix === 'None' ? '' : suffix,
        blood_type: bloodType,
        age: parseInt(age, 10),
        gender,
        contact: contact.trim(),
        barangay: savedBarangay,
        municipality: municipality.trim(),
        province: province.trim(),
        mbd_type: mbdType,
        mbd_on: mbdOn,
        mbd_date: savedMbd,
        status,
        serial_number: status === 'passed' ? serialNumber.trim() : '',
        reserved_for_px: status === 'passed' ? titleCaseText(reservedForPatient) : '',
        reason_for_deferral: status === 'deferred' ? deferralReason : '',
        reason_for_deferral_custom: status === 'deferred' && deferralReason === 'Custom Reason'
          ? titleCaseText(customDeferralReason)
          : '',
      });
      setMessage(data.message);
      navigation.replace('DonorDetail', { donorId: data.donor.id, donorName: data.donor.name });
    } catch (error) {
      setMessage(error.message);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.loading}>
        <ActivityIndicator color="#990000" />
      </View>
    );
  }

  const bloodTypes = meta?.bloodTypes ?? BLOOD_TYPES;
  const deferralReasons = meta?.deferralReasons ?? DEFERRAL_REASONS;
  const provinces = meta?.provinces ?? [];
  const provinceOptions = province && !provinces.includes(province) ? [...provinces, province] : provinces;
  const municipalities = meta?.municipalitiesByProvince?.[province] ?? [];
  const municipalityOptions = municipality && !municipalities.includes(municipality)
    ? [...municipalities, municipality]
    : municipalities;
  const knownBarangays = meta?.places?.[municipality] ?? [];
  const barangayOptions = barangay && !knownBarangays.includes(barangay)
    ? [...knownBarangays, barangay]
    : knownBarangays;

  return (
    <ScrollView style={styles.root} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      {!donorId ? (
        <View style={styles.hero}>
          <View style={styles.heroIcon}><Text style={styles.heroIconText}>+</Text></View>
          <View style={styles.heroCopy}>
            <Text style={styles.heroEyebrow}>BLOOD COLLECTION</Text>
            <Text style={styles.heroTitle}>First-time donor</Text>
            <Text style={styles.heroSubtitle}>Complete the donor profile and screening record.</Text>
          </View>
        </View>
      ) : null}

      {message ? <Text style={styles.message}>{message}</Text> : null}

      <View style={styles.sectionCard}>
        <SectionHeading number="01" title={donorId ? 'Donor profile' : 'Name & contact'} />
        {donorId && !structuredName ? (
          <FormField label="Full name">
            <TextInput style={styles.input} value={fullName} onChangeText={(value) => setFullName(capitalizeWords(value))} placeholder="Full name" autoCapitalize="words" />
          </FormField>
        ) : (
          <>
        <View style={styles.fieldRow}>
          <FormField label="Last name / Apilyedo *" style={styles.halfField}>
            <TextInput style={styles.input} value={lastName} onChangeText={(value) => setLastName(capitalizeWords(value))} placeholder="Last name" autoCapitalize="words" />
          </FormField>
          <FormField label="First name *" style={styles.halfField}>
            <TextInput style={styles.input} value={firstName} onChangeText={(value) => setFirstName(capitalizeWords(value))} placeholder="First name" autoCapitalize="words" />
          </FormField>
        </View>
        <View style={styles.fieldRow}>
          <FormField label="Middle name" style={styles.halfField}>
            <TextInput style={styles.input} value={middleName} onChangeText={(value) => setMiddleName(capitalizeWords(value))} placeholder="Middle name" autoCapitalize="words" />
          </FormField>
          <FormField label="Suffix / title" style={styles.halfField}>
            <SelectField value={suffix} options={SUFFIXES} onChange={setSuffix} placeholder="Select suffix" />
          </FormField>
        </View>
          </>
        )}
        <FormField label="Contact # *">
          <TextInput
            style={styles.input}
            value={contact}
            onChangeText={setContact}
            keyboardType="phone-pad"
            placeholder="Contact number"
          />
        </FormField>
      </View>

      <View style={styles.sectionCard}>
        <SectionHeading number="02" title={donorId ? 'Personal information' : 'Event & personal info'} />
        <FormField label="MBD / Date *">
          <View style={styles.mbdControlRow}>
            <View style={styles.mbdTypeControl}>
              <SelectField value={mbdType} options={['MBD', 'WI']} onChange={setMbdType} placeholder="MBD or WI" />
            </View>
            <DateTextField
              value={mbdOn}
              onChangeText={setMbdOn}
              onPressCalendar={openMbdDatePicker}
              accessibilityLabel="MBD date"
              style={styles.mbdDateControl}
            />
          </View>
          {Platform.OS === 'android' && mbdDatePickerVisible ? (
            <DateTimePicker
              value={pendingMbdDate}
              mode="date"
              display="default"
              onChange={handleMbdDatePickerChange}
            />
          ) : null}
          <Modal
            visible={Platform.OS === 'ios' && mbdDatePickerVisible}
            transparent
            animationType="fade"
            onRequestClose={() => setMbdDatePickerVisible(false)}
          >
            <Pressable style={styles.modalBackdrop} onPress={() => setMbdDatePickerVisible(false)}>
              <Pressable style={styles.pickerCard} onPress={(event) => event.stopPropagation()}>
                <Text style={styles.pickerTitle}>Select MBD date</Text>
                <DateTimePicker
                  value={pendingMbdDate}
                  mode="date"
                  display="spinner"
                  onChange={handleMbdDatePickerChange}
                />
                <View style={styles.datePickerActions}>
                  <Pressable
                    style={styles.datePickerCancel}
                    onPress={() => setMbdDatePickerVisible(false)}
                    accessibilityRole="button"
                  >
                    <Text style={styles.pickerCancelText}>Cancel</Text>
                  </Pressable>
                  <Pressable
                    style={styles.datePickerConfirm}
                    onPress={() => {
                      setMbdOn(formatDate(pendingMbdDate));
                      setMbdDatePickerVisible(false);
                    }}
                    accessibilityRole="button"
                  >
                    <Text style={styles.datePickerConfirmText}>Done</Text>
                  </Pressable>
                </View>
              </Pressable>
            </Pressable>
          </Modal>
        </FormField>
        <View style={styles.fieldRow}>
          <FormField label="Age *" style={styles.halfField}>
            <TextInput
              style={styles.input}
              value={age}
              onChangeText={setAge}
              keyboardType="number-pad"
              placeholder="Age"
            />
          </FormField>
          <FormField label="Sex *" style={styles.halfField}>
            <SelectField value={gender} options={GENDERS} onChange={setGender} placeholder="Select sex" />
          </FormField>
        </View>
        <FormField label="Blood type *">
          <View style={styles.bloodTypeGrid}>
            {bloodTypes.map((type) => (
              <Pressable
                key={type}
                style={[styles.bloodTypeChip, bloodType === type && styles.bloodTypeChipActive]}
                onPress={() => setBloodType(type)}
                accessibilityRole="button"
                accessibilityState={{ selected: bloodType === type }}
              >
                <Text style={[styles.bloodTypeText, bloodType === type && styles.bloodTypeTextActive]}>{type}</Text>
              </Pressable>
            ))}
          </View>
        </FormField>
      </View>

      <View style={styles.sectionCard}>
        <SectionHeading number="03" title="Address" />
        <FormField label="Province *">
          <SelectField
            value={province}
            options={provinceOptions}
            onChange={(value) => {
              setProvince(value);
              setMunicipality('');
              setBarangay('');
            }}
            placeholder="Select province"
          />
        </FormField>
        <FormField label="Municipality / City *">
          <SelectField
            value={municipality}
            options={municipalityOptions}
            onChange={(value) => {
              setMunicipality(value);
              setBarangay('');
            }}
            placeholder={province ? 'Select municipality' : 'Select province first'}
            disabled={!province}
          />
        </FormField>
        <FormField label="Barangay *">
          {knownBarangays.length > 0 ? (
            <SelectField
              value={barangay}
              options={barangayOptions}
              onChange={setBarangay}
              placeholder="Select barangay"
            />
          ) : (
            <TextInput
              style={styles.input}
              value={barangay}
              onChangeText={(value) => setBarangay(capitalizeWords(value))}
              placeholder={municipality ? 'Barangay' : 'Select municipality first'}
              autoCapitalize="words"
              editable={Boolean(municipality)}
            />
          )}
        </FormField>
      </View>

      <View style={styles.sectionCard}>
        <SectionHeading number="04" title="Donation outcome" />
        <Text style={styles.label}>Donor status *</Text>
        <View style={styles.statusRow}>
          {[
            { value: 'passed', label: 'PASSED' },
            { value: 'deferred', label: 'DEFERRED' },
          ].map((option) => (
            <Pressable
              key={option.value}
              style={[styles.statusButton, status === option.value && styles.statusButtonActive]}
              onPress={() => setStatus(option.value)}
              accessibilityRole="button"
              accessibilityState={{ selected: status === option.value }}
            >
              <Text style={[styles.statusButtonText, status === option.value && styles.statusButtonTextActive]}>
                {option.label}
              </Text>
            </Pressable>
          ))}
        </View>

        {status === 'passed' ? (
          <>
            <FormField label="Serial # *">
              <TextInput
                style={styles.input}
                value={serialNumber}
                onChangeText={setSerialNumber}
                placeholder="Blood bag barcode"
                autoCapitalize="characters"
              />
            </FormField>
            <FormField label="Reserved for patient (optional)">
              <TextInput
                style={styles.input}
                value={reservedForPatient}
                onChangeText={(value) => setReservedForPatient(capitalizeWords(value))}
                placeholder="Patient name"
                autoCapitalize="words"
              />
            </FormField>
          </>
        ) : status === 'deferred' ? (
          <>
            <FormField label="Reason for deferral *">
              <SelectField
                value={deferralReason}
                options={deferralReasons}
                onChange={(value) => {
                  setDeferralReason(value);
                  if (value !== 'Custom Reason') {
                    setCustomDeferralReason('');
                  }
                }}
                placeholder="Select reason"
              />
            </FormField>
            {deferralReason === 'Custom Reason' ? (
              <FormField label="Custom reason *">
                <TextInput
                  style={styles.input}
                  value={customDeferralReason}
                  onChangeText={(value) => setCustomDeferralReason(capitalizeWords(value))}
                  placeholder="Enter reason"
                  autoCapitalize="words"
                />
              </FormField>
            ) : null}
          </>
        ) : null}
      </View>

      <Pressable
        style={[styles.button, saving && styles.buttonDisabled]}
        onPress={saveDonor}
        disabled={saving}
        accessibilityRole="button"
      >
        <Text style={styles.buttonText}>
          {saving ? 'Saving…' : donorId ? 'Save changes' : 'Save donor record'}
        </Text>
      </Pressable>
    </ScrollView>
  );
}

function SectionHeading({ number, title }) {
  return (
    <View style={styles.sectionHeading}>
      <Text style={styles.sectionNumber}>{number}</Text>
      <Text style={styles.sectionTitle}>{title}</Text>
    </View>
  );
}

function FormField({ label, style, children }) {
  return (
    <View style={[styles.field, style]}>
      <Text style={styles.label}>{label}</Text>
      {children}
    </View>
  );
}

function DateTextField({ value, onChangeText, onPressCalendar, accessibilityLabel, style }) {
  if (Platform.OS === 'web') {
    return (
      <TextInput
        style={[styles.input, style]}
        value={value}
        onChangeText={onChangeText}
        type="date"
        accessibilityLabel={accessibilityLabel}
      />
    );
  }

  return (
    <View style={[styles.dropdown, style]}>
      <TextInput
        style={styles.dateTextInput}
        value={value}
        onChangeText={onChangeText}
        placeholder="YYYY-MM-DD"
        placeholderTextColor="#94a3b8"
        keyboardType="numbers-and-punctuation"
        maxLength={10}
        accessibilityLabel={accessibilityLabel}
      />
      <Pressable
        style={styles.datePickerButton}
        onPress={onPressCalendar}
        accessibilityRole="button"
        accessibilityLabel={`Choose ${accessibilityLabel.toLowerCase()} from calendar`}
      >
        <Text style={styles.datePickerIcon} accessibilityElementsHidden>▦</Text>
      </Pressable>
    </View>
  );
}

function SelectField({ value, options, onChange, placeholder, disabled = false }) {
  const [visible, setVisible] = useState(false);
  const isDisabled = disabled || options.length === 0;

  return (
    <>
      <Pressable
        style={[styles.dropdown, isDisabled && styles.dropdownDisabled]}
        onPress={() => setVisible(true)}
        disabled={isDisabled}
        accessibilityRole="button"
        accessibilityLabel={value || placeholder}
        accessibilityState={{ expanded: visible, disabled: isDisabled }}
      >
        <Text style={value ? styles.dropdownValue : styles.dropdownPlaceholder}>
          {value || placeholder}
        </Text>
        <Text style={styles.dropdownArrow}>⌄</Text>
      </Pressable>
      <Modal visible={visible} transparent animationType="fade" onRequestClose={() => setVisible(false)}>
        <Pressable style={styles.modalBackdrop} onPress={() => setVisible(false)}>
          <Pressable style={styles.pickerCard} onPress={(event) => event.stopPropagation()}>
            <Text style={styles.pickerTitle}>{placeholder}</Text>
            <ScrollView style={styles.pickerOptions}>
              {options.map((option) => (
                <Pressable
                  key={option}
                  style={[styles.pickerOption, option === value && styles.pickerOptionSelected]}
                  onPress={() => {
                    onChange(option);
                    setVisible(false);
                  }}
                  accessibilityRole="button"
                  accessibilityState={{ selected: option === value }}
                >
                  <Text style={[styles.pickerOptionText, option === value && styles.pickerOptionTextSelected]}>
                    {option}
                  </Text>
                  {option === value ? <Text style={styles.pickerCheck}>✓</Text> : null}
                </Pressable>
              ))}
            </ScrollView>
            <Pressable style={styles.pickerCancel} onPress={() => setVisible(false)} accessibilityRole="button">
              <Text style={styles.pickerCancelText}>Cancel</Text>
            </Pressable>
          </Pressable>
        </Pressable>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#f8fafc' },
  content: { padding: 16, paddingBottom: 30, gap: 13 },
  loading: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: '#f8fafc' },
  hero: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 13,
    padding: 16,
    borderWidth: 1,
    borderColor: '#f3d5d8',
    borderRadius: 16,
    backgroundColor: '#fff7f7',
  },
  heroIcon: {
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 13,
    backgroundColor: '#990000',
  },
  heroIconText: { color: '#fff', fontSize: 28, lineHeight: 32, fontWeight: '500' },
  heroCopy: { flex: 1 },
  heroEyebrow: { color: '#990000', fontSize: 10, letterSpacing: 1.1, fontWeight: '800' },
  heroTitle: { color: '#1e293b', fontSize: 20, fontWeight: '800', marginTop: 2 },
  heroSubtitle: { color: '#64748b', fontSize: 12, marginTop: 3, lineHeight: 17 },
  sectionCard: {
    padding: 15,
    gap: 11,
    borderWidth: 1,
    borderColor: '#e8edf3',
    borderRadius: 15,
    backgroundColor: '#fff',
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.035,
    shadowRadius: 5,
    elevation: 1,
  },
  sectionHeading: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 9,
    paddingBottom: 9,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
    marginBottom: 1,
  },
  sectionNumber: { color: '#990000', fontSize: 10, fontWeight: '800' },
  sectionTitle: { color: '#1e293b', fontSize: 15, fontWeight: '800' },
  fieldRow: { flexDirection: 'row', gap: 10 },
  mbdControlRow: { flexDirection: 'row', gap: 10 },
  mbdTypeControl: { width: 110 },
  mbdDateControl: { flex: 1, minWidth: 0 },
  dateTextInput: {
    flex: 1,
    minWidth: 0,
    paddingHorizontal: 0,
    paddingVertical: 0,
    borderWidth: 0,
    color: '#1e293b',
    backgroundColor: 'transparent',
    fontSize: 14,
  },
  datePickerButton: { minWidth: 28, alignItems: 'center', justifyContent: 'center' },
  field: { gap: 5 },
  halfField: { flex: 1, minWidth: 0 },
  dropdownDisabled: { backgroundColor: '#f8fafc', opacity: 0.7 },
  label: { color: '#475569', fontSize: 12, fontWeight: '700' },
  datePickerIcon: { color: '#64748b', fontSize: 16 },
  input: {
    minHeight: 46,
    borderWidth: 1,
    borderColor: '#dbe3ec',
    borderRadius: 10,
    paddingHorizontal: 11,
    paddingVertical: 10,
    color: '#1e293b',
    backgroundColor: '#fff',
    fontSize: 14,
  },
  dropdown: {
    minHeight: 46,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderWidth: 1,
    borderColor: '#dbe3ec',
    borderRadius: 10,
    paddingHorizontal: 12,
    backgroundColor: '#fff',
  },
  dropdownValue: { color: '#1e293b', fontSize: 14 },
  dropdownPlaceholder: { color: '#94a3b8', fontSize: 14 },
  dropdownArrow: { color: '#64748b', fontSize: 18, lineHeight: 20 },
  bloodTypeGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  bloodTypeChip: {
    minWidth: 52,
    minHeight: 38,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#dbe3ec',
    borderRadius: 10,
    backgroundColor: '#fff',
  },
  bloodTypeChipActive: { borderColor: '#990000', backgroundColor: '#fff1f2' },
  bloodTypeText: { color: '#475569', fontSize: 13, fontWeight: '700' },
  bloodTypeTextActive: { color: '#990000', fontWeight: '800' },
  statusRow: { flexDirection: 'row', gap: 9 },
  statusButton: {
    flex: 1,
    minHeight: 42,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#dbe3ec',
    borderRadius: 10,
    backgroundColor: '#fff',
  },
  statusButtonActive: { borderColor: '#990000', backgroundColor: '#fff1f2' },
  statusButtonText: { color: '#64748b', fontSize: 12, fontWeight: '800', letterSpacing: 0.3 },
  statusButtonTextActive: { color: '#990000' },
  message: {
    color: '#b91c1c',
    borderWidth: 1,
    borderColor: '#fecaca',
    borderRadius: 10,
    backgroundColor: '#fff5f5',
    padding: 11,
    fontSize: 13,
    lineHeight: 18,
  },
  modalBackdrop: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    backgroundColor: 'rgba(15, 23, 42, 0.48)',
  },
  pickerCard: {
    width: '100%',
    maxWidth: 380,
    maxHeight: '75%',
    borderRadius: 16,
    padding: 16,
    backgroundColor: '#fff',
  },
  pickerTitle: { color: '#1e293b', fontSize: 16, fontWeight: '800', marginBottom: 8 },
  pickerOptions: { flexGrow: 0 },
  pickerOption: {
    minHeight: 44,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
  },
  pickerOptionSelected: { backgroundColor: '#fff5f5' },
  pickerOptionText: { color: '#334155', fontSize: 14 },
  pickerOptionTextSelected: { color: '#990000', fontWeight: '700' },
  pickerCheck: { color: '#990000', fontWeight: '800' },
  pickerCancel: { minHeight: 42, alignItems: 'center', justifyContent: 'center', marginTop: 4 },
  pickerCancelText: { color: '#64748b', fontSize: 13, fontWeight: '700' },
  datePickerActions: { flexDirection: 'row', gap: 10, marginTop: 8 },
  datePickerCancel: {
    flex: 1,
    minHeight: 42,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    backgroundColor: '#f1f5f9',
  },
  datePickerConfirm: {
    flex: 1,
    minHeight: 42,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    backgroundColor: '#990000',
  },
  datePickerConfirmText: { color: '#fff', fontSize: 13, fontWeight: '700' },
  button: {
    minHeight: 49,
    backgroundColor: '#990000',
    borderRadius: 12,
    paddingHorizontal: 16,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#7a0000',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.16,
    shadowRadius: 5,
    elevation: 2,
  },
  buttonDisabled: { opacity: 0.65 },
  buttonText: { color: '#fff', fontWeight: '800', fontSize: 15 },
});
