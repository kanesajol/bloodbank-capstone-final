import { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

export default function PasswordInput({
  accessibilityLabel,
  autoComplete,
  onChangeText,
  placeholder,
  placeholderTextColor,
  style,
  textInputStyle,
  value,
}) {
  const [isVisible, setIsVisible] = useState(false);

  return (
    <View style={[styles.row, style]}>
      <TextInput
        accessibilityLabel={accessibilityLabel}
        autoComplete={autoComplete}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={placeholderTextColor}
        secureTextEntry={!isVisible}
        style={[styles.input, textInputStyle]}
        value={value}
      />
      <Pressable
        accessibilityHint={isVisible ? 'Hides the password' : 'Shows the password'}
        accessibilityLabel={isVisible ? 'Hide password' : 'Show password'}
        accessibilityRole="button"
        accessibilityState={{ expanded: isVisible }}
        hitSlop={8}
        onPress={() => setIsVisible((visible) => !visible)}
        style={styles.toggle}
      >
        <Text style={styles.toggleText}>{isVisible ? 'Hide' : 'Show'}</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  input: {
    flex: 1,
    minWidth: 0,
    paddingHorizontal: 0,
    paddingVertical: 0,
    fontSize: 16,
    color: '#171717',
  },
  toggle: {
    paddingLeft: 12,
    paddingVertical: 8,
  },
  toggleText: {
    color: '#990000',
    fontSize: 13,
    fontWeight: '700',
  },
});
