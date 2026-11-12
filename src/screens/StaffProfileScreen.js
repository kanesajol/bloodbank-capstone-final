import { useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { changeCurrentPassword, updateCurrentProfile } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { colors } from '../theme/layout';
import { capitalizeWords } from '../collectionForm';
import PasswordInput from '../components/PasswordInput';

export default function StaffProfileScreen() {
  const { user, applyUserUpdate } = useAuth();
  const scrollRef = useRef(null);
  const [name, setName] = useState(user?.name ?? '');
  const [email, setEmail] = useState(user?.email ?? '');
  const [contact, setContact] = useState(user?.contact ?? '');
  const [role, setRole] = useState(user?.role ?? '');
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [savingProfile, setSavingProfile] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const saveProfile = async () => {
    setSavingProfile(true);
    setMessage('');
    setError('');
    try {
      const result = await updateCurrentProfile({
        name: name.trim(),
        email: email.trim(),
        contact: contact.trim(),
        role,
      });
      applyUserUpdate(result.user);
      setMessage('Profile updated.');
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSavingProfile(false);
    }
  };

  const savePassword = async () => {
    setSavingPassword(true);
    setMessage('');
    setError('');
    try {
      await changeCurrentPassword({
        current_password: currentPassword,
        password,
        password_confirmation: passwordConfirmation,
      });
      setCurrentPassword('');
      setPassword('');
      setPasswordConfirmation('');
      setMessage('Password changed.');
      scrollRef.current?.scrollTo({ y: 0, animated: true });
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSavingPassword(false);
    }
  };

  return (
    <View style={styles.root}>
    {message ? <Text accessibilityRole="alert" style={styles.bannerSuccess}>{message}</Text> : null}
    {error ? <Text accessibilityRole="alert" style={styles.bannerError}>{error}</Text> : null}
    <ScrollView ref={scrollRef} style={styles.scroll} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <View style={styles.heading}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{user?.avatar_initials || user?.name?.trim().charAt(0) || 'S'}</Text>
        </View>
        <View style={styles.headingCopy}>
          <Text style={styles.title}>My Profile</Text>
          <Text style={styles.subtitle}>Manage your staff account details</Text>
        </View>
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Account details</Text>
        <Text style={styles.label}>Full name</Text>
        <TextInput style={styles.input} value={name} onChangeText={(value) => setName(capitalizeWords(value))} autoCapitalize="words" />
        <Text style={styles.label}>Work email</Text>
        <TextInput style={styles.input} value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" />
        <Text style={styles.label}>Contact number</Text>
        <TextInput style={styles.input} value={contact} onChangeText={setContact} keyboardType="phone-pad" />
        <Text style={styles.label}>Username</Text>
        <Text style={styles.readonly}>@{user?.username}</Text>
        <Text style={styles.label}>Role</Text>
        <TextInput style={styles.input} value={role} onChangeText={(value) => setRole(capitalizeWords(value))} autoCapitalize="words" />
        <Pressable style={[styles.button, savingProfile && styles.buttonDisabled]} onPress={saveProfile} disabled={savingProfile}>
          <Text style={styles.buttonText}>{savingProfile ? 'Saving…' : 'Save profile'}</Text>
        </Pressable>
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Change password</Text>
        <Text style={styles.label}>Current password</Text>
        <PasswordInput
          accessibilityLabel="Current password"
          autoComplete="current-password"
          style={styles.input}
          textInputStyle={styles.passwordText}
          value={currentPassword}
          onChangeText={setCurrentPassword}
        />
        <Text style={styles.label}>New password</Text>
        <PasswordInput
          accessibilityLabel="New password"
          autoComplete="new-password"
          style={styles.input}
          textInputStyle={styles.passwordText}
          value={password}
          onChangeText={setPassword}
        />
        <Text style={styles.label}>Confirm new password</Text>
        <PasswordInput
          accessibilityLabel="Confirm new password"
          autoComplete="new-password"
          style={styles.input}
          textInputStyle={styles.passwordText}
          value={passwordConfirmation}
          onChangeText={setPasswordConfirmation}
        />
        <Pressable style={[styles.button, savingPassword && styles.buttonDisabled]} onPress={savePassword} disabled={savingPassword}>
          <Text style={styles.buttonText}>{savingPassword ? 'Updating…' : 'Change password'}</Text>
        </Pressable>
      </View>
    </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#fff' },
  scroll: { flex: 1 },
  content: { padding: 16, paddingBottom: 30, gap: 14 },
  bannerSuccess: { margin: 12, marginBottom: 0, padding: 12, borderRadius: 10, color: '#166534', backgroundColor: '#f0fdf4', fontSize: 14, fontWeight: '700' },
  bannerError: { margin: 12, marginBottom: 0, padding: 12, borderRadius: 10, color: colors.danger, backgroundColor: '#fff5f5', fontSize: 14, fontWeight: '700' },
  heading: { flexDirection: 'row', alignItems: 'center', gap: 11, marginBottom: 3 },
  avatar: { width: 46, height: 46, borderRadius: 15, alignItems: 'center', justifyContent: 'center', backgroundColor: '#fff1f1' },
  avatarText: { color: colors.primary, fontSize: 18, fontWeight: '800' },
  headingCopy: { flex: 1 },
  title: { color: colors.primary, fontSize: 19, fontWeight: '800' },
  subtitle: { color: colors.muted, fontSize: 12, marginTop: 3 },
  card: { gap: 9, padding: 15, borderWidth: 1, borderColor: '#f1dede', borderRadius: 15, backgroundColor: '#fff' },
  sectionTitle: { color: colors.text, fontSize: 15, fontWeight: '800', marginBottom: 2 },
  label: { color: '#475569', fontSize: 12, fontWeight: '700' },
  input: { minHeight: 44, paddingHorizontal: 11, borderWidth: 1, borderColor: colors.border, borderRadius: 10, color: colors.text, backgroundColor: '#fff', fontSize: 14 },
  passwordText: { fontSize: 14, color: colors.text },
  readonly: { color: colors.text, fontSize: 13, fontWeight: '600', paddingVertical: 3 },
  button: { minHeight: 46, alignItems: 'center', justifyContent: 'center', marginTop: 5, borderRadius: 11, backgroundColor: colors.primary },
  buttonDisabled: { opacity: 0.65 },
  buttonText: { color: '#fff', fontSize: 14, fontWeight: '800' },
});
